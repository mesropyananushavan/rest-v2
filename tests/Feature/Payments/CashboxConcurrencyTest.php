<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Models\Permission;
use App\Modules\Identity\Infrastructure\Models\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Identity\Infrastructure\Models\UserBranchAssignment;
use App\Modules\Payments\Application\CreateCashbox;
use App\Modules\Payments\Infrastructure\Models\Cashbox;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Modules\Tenancy\Infrastructure\Models\Branch;
use App\Modules\Tenancy\Infrastructure\Models\Tenant;
use App\Support\Logging\LogContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\Process\Process;
use Tests\Support\Payments\ConcurrentCashboxWorker;

beforeEach(function (): void {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Cashbox concurrency correctness is PostgreSQL-only.');
    }

    Artisan::call('migrate:fresh', ['--force' => true]);
    DB::statement("set lock_timeout = '1500ms'");
    DB::statement("set statement_timeout = '10000ms'");
});

afterEach(function (): void {
    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();
    LogContext::clear();
});

it('does not create multiple defaults during concurrent first cashbox creation', function (): void {
    $record = cashboxConcurrencyFixture();
    $prefix = cashboxConcurrencyPrefix('first-default');
    $startFile = "{$prefix}.start";

    $workerA = cashboxConcurrencyStartWorker(cashboxConcurrencyPayload($record, $startFile, [
        'mode' => 'create_cashbox',
        'name' => 'First Register A',
    ]));
    $workerB = cashboxConcurrencyStartWorker(cashboxConcurrencyPayload($record, $startFile, [
        'mode' => 'create_cashbox',
        'name' => 'First Register B',
    ]));

    touch($startFile);

    $results = [cashboxConcurrencyWaitFor($workerA), cashboxConcurrencyWaitFor($workerB)];
    cashboxConcurrencyActingIn($record);

    expect(cashboxConcurrencyCountSuccesses($results))->toBe(2)
        ->and(Cashbox::query()->where('branch_id', (int) $record['branch']->id)->where('is_active', true)->count())->toBe(2)
        ->and(Cashbox::query()->where('branch_id', (int) $record['branch']->id)->where('is_active', true)->where('is_default', true)->count())->toBe(1);
});

it('normalizes concurrent duplicate active cashbox creation to one success and one stable failure', function (): void {
    $record = cashboxConcurrencyFixture();
    $prefix = cashboxConcurrencyPrefix('duplicate-name');
    $startFile = "{$prefix}.start";

    $workerA = cashboxConcurrencyStartWorker(cashboxConcurrencyPayload($record, $startFile, [
        'mode' => 'create_cashbox',
        'name' => 'Duplicate Register',
    ]));
    $workerB = cashboxConcurrencyStartWorker(cashboxConcurrencyPayload($record, $startFile, [
        'mode' => 'create_cashbox',
        'name' => 'duplicate register',
    ]));

    touch($startFile);

    $results = [cashboxConcurrencyWaitFor($workerA), cashboxConcurrencyWaitFor($workerB)];
    cashboxConcurrencyActingIn($record);

    expect(cashboxConcurrencyCountSuccesses($results))->toBe(1)
        ->and(cashboxConcurrencyCountDomainCode($results, 'payments.cashbox_name_duplicate'))->toBe(1)
        ->and(Cashbox::query()->where('branch_id', (int) $record['branch']->id)->where('is_active', true)->count())->toBe(1);
});

it('leaves exactly one default after concurrent competing default selections', function (): void {
    $record = cashboxConcurrencyFixture();
    cashboxConcurrencyActingIn($record);

    $main = app(CreateCashbox::class)('Main');
    $bar = app(CreateCashbox::class)('Bar');
    $patio = app(CreateCashbox::class)('Patio');
    $prefix = cashboxConcurrencyPrefix('competing-default');
    $startFile = "{$prefix}.start";

    expect((int) $main->id)->toBeGreaterThan(0);

    $workerA = cashboxConcurrencyStartWorker(cashboxConcurrencyPayload($record, $startFile, [
        'mode' => 'select_default',
        'cashbox_id' => (int) $bar->id,
    ]));
    $workerB = cashboxConcurrencyStartWorker(cashboxConcurrencyPayload($record, $startFile, [
        'mode' => 'select_default',
        'cashbox_id' => (int) $patio->id,
    ]));

    touch($startFile);

    $results = [cashboxConcurrencyWaitFor($workerA), cashboxConcurrencyWaitFor($workerB)];

    expect(cashboxConcurrencyCountSuccesses($results))->toBe(2)
        ->and(Cashbox::query()->where('branch_id', (int) $record['branch']->id)->where('is_active', true)->where('is_default', true)->count())->toBe(1)
        ->and(Cashbox::query()->where('branch_id', (int) $record['branch']->id)->where('is_default', true)->value('id'))
        ->toBeIn([(int) $bar->id, (int) $patio->id]);
});

/**
 * @return array{tenant: Tenant, branch: Branch, user: User}
 */
function cashboxConcurrencyFixture(): array
{
    $tenant = Tenant::query()->create([
        'name' => 'Cashbox Concurrency Tenant',
        'slug' => 'cashbox-concurrency-'.str()->random(8),
        'default_locale' => 'en',
        'currency' => 'AMD',
        'status' => 'active',
    ]);

    app(TenantResolver::class)->set((int) $tenant->id);

    $branch = Branch::query()->create([
        'name' => 'Cashbox Concurrency Branch',
        'timezone' => 'Asia/Yerevan',
        'status' => 'active',
    ]);

    $role = Role::query()->create([
        'code' => 'cashbox-concurrency-role',
        'name' => 'Cashbox Concurrency Role',
    ]);
    $permission = Permission::query()->create([
        'code' => 'payments.cashboxes.manage',
        'name' => 'Manage cashboxes',
    ]);
    $role->permissions()->attach((int) $permission->id, ['tenant_id' => (int) $tenant->id]);

    $user = User::query()->create([
        'role_id' => (int) $role->id,
        'name' => 'Cashbox Concurrency Manager',
        'email' => 'cashbox-concurrency-'.str()->random(8).'@smartrest.test',
        'username' => 'cashbox-concurrency-'.str()->random(8),
        'default_locale' => 'en',
        'active' => true,
        'is_superadmin' => false,
        'password' => Hash::make('password'),
    ]);

    UserBranchAssignment::query()->create([
        'user_id' => (int) $user->id,
        'branch_id' => (int) $branch->id,
    ]);

    app(TenantResolver::class)->clear();
    app(BranchContext::class)->clear();

    return [
        'tenant' => $tenant,
        'branch' => $branch,
        'user' => $user,
    ];
}

/**
 * @param  array{tenant: Tenant, branch: Branch, user: User}  $record
 */
function cashboxConcurrencyActingIn(array $record): void
{
    app(TenantResolver::class)->set((int) $record['tenant']->id);
    app(BranchContext::class)->set((int) $record['branch']->id);
    auth()->login($record['user']);
    LogContext::start('cashbox-concurrency-parent', 'payments');
}

/**
 * @param  array{tenant: Tenant, branch: Branch, user: User}  $record
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function cashboxConcurrencyPayload(array $record, string $startFile, array $overrides): array
{
    return [
        'tenant_id' => (int) $record['tenant']->id,
        'branch_id' => (int) $record['branch']->id,
        'user_id' => (int) $record['user']->id,
        'request_id' => 'cashbox-concurrency-worker',
        'start_file' => $startFile,
    ] + $overrides;
}

/**
 * @param  array<string, mixed>  $payload
 */
function cashboxConcurrencyStartWorker(array $payload): Process
{
    $process = new Process(ConcurrentCashboxWorker::command($payload), base_path(), [
        'APP_ENV' => 'testing',
        'CACHE_STORE' => 'array',
        'QUEUE_CONNECTION' => 'sync',
        'SESSION_DRIVER' => 'array',
        'DB_CONNECTION' => 'pgsql',
        'DB_HOST' => (string) config('database.connections.pgsql.host'),
        'DB_PORT' => (string) config('database.connections.pgsql.port'),
        'DB_DATABASE' => (string) config('database.connections.pgsql.database'),
        'DB_USERNAME' => (string) config('database.connections.pgsql.username'),
        'DB_PASSWORD' => (string) config('database.connections.pgsql.password'),
    ]);
    $process->setTimeout(15);
    $process->start();

    return $process;
}

/**
 * @return array<string, mixed>
 */
function cashboxConcurrencyWaitFor(Process $process): array
{
    $process->wait();

    expect($process->getErrorOutput())->toBe('');

    $lines = array_values(array_filter(explode("\n", trim($process->getOutput()))));
    $lastLine = end($lines);

    expect($lastLine)->toBeString();

    $decoded = json_decode((string) $lastLine, true, flags: JSON_THROW_ON_ERROR);

    expect($decoded)->toBeArray();

    /** @var array<string, mixed> $decoded */
    return $decoded;
}

function cashboxConcurrencyPrefix(string $name): string
{
    return storage_path('framework/testing/cashboxes-'.$name.'-'.bin2hex(random_bytes(6)));
}

/**
 * @param  list<array<string, mixed>>  $results
 */
function cashboxConcurrencyCountSuccesses(array $results): int
{
    return collect($results)
        ->filter(fn (array $result): bool => ($result['ok'] ?? null) === true)
        ->count();
}

/**
 * @param  list<array<string, mixed>>  $results
 */
function cashboxConcurrencyCountDomainCode(array $results, string $code): int
{
    return collect($results)
        ->filter(fn (array $result): bool => ($result['domain_code'] ?? null) === $code)
        ->count();
}
