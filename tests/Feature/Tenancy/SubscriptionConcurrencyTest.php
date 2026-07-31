<?php

declare(strict_types=1);

use App\Modules\Tenancy\Application\RecordTenantSubscriptionPayment;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Modules\Tenancy\Infrastructure\Models\Tenant;
use App\Modules\Tenancy\Infrastructure\Models\TenantSubscription;
use App\Support\Audit\AuditLog;
use App\Support\Logging\LogContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

beforeEach(function (): void {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Subscription concurrency correctness is PostgreSQL-only.');
    }

    Artisan::call('migrate:fresh', ['--force' => true]);
    subscriptionConcurrencySetTimeouts();
});

afterEach(function (): void {
    if (DB::connection()->getDriverName() === 'pgsql') {
        Artisan::call('migrate:fresh', ['--force' => true]);
    }

    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();
    LogContext::clear();
});

it('holds the tenant lock while recording subscription payments', function (): void {
    $tenant = subscriptionConcurrencyTenant('payment-lock');
    subscriptionConcurrencySubscription($tenant, '2026-08-01', 3);
    $prefix = subscriptionConcurrencyPrefix('payment-lock');
    $startFile = "{$prefix}.start";
    $readyFile = "{$prefix}.ready";

    DB::beginTransaction();

    try {
        DB::statement('select id from tenants where id = ? for update', [(int) $tenant->id]);
        $process = subscriptionConcurrencyStartWorker([
            'mode' => 'record_payment',
            'tenant_id' => (int) $tenant->id,
            'payment_date' => '2026-08-05 00:00:00 Asia/Yerevan',
            'expected_next_due_on' => '2026-08-01 00:00:00 Asia/Yerevan',
            'start_file' => $startFile,
            'ready_file' => $readyFile,
            'application_name' => 'subscription-payment-lock-'.bin2hex(random_bytes(4)),
        ]);

        touch($startFile);
        subscriptionConcurrencyWaitForReadyFile($readyFile, $process);
        usleep(300_000);

        expect($process->isRunning())->toBeTrue()
            ->and(subscriptionConcurrencyNextDueOn($tenant))->toBe('2026-08-01');

        DB::commit();
    } catch (Throwable $exception) {
        DB::rollBack();

        if (isset($process) && $process->isRunning()) {
            $process->stop(1);
        }

        throw $exception;
    }

    $result = subscriptionConcurrencyWaitFor($process);

    expect($result['ok'])->toBeTrue()
        ->and($result['next_due_on'])->toBe('2026-09-01')
        ->and(subscriptionConcurrencyNextDueOn($tenant))->toBe('2026-09-01');
});

it('does not suspend tenants whose payment wins while automatic suspension waits for the tenant lock', function (): void {
    $tenant = subscriptionConcurrencyTenant('payment-race');
    subscriptionConcurrencySubscription($tenant, '2026-08-01', 3);
    $prefix = subscriptionConcurrencyPrefix('payment-race');
    $startFile = "{$prefix}.start";
    $readyFile = "{$prefix}.ready";

    DB::beginTransaction();

    try {
        DB::statement('select id from tenants where id = ? for update', [(int) $tenant->id]);
        $process = subscriptionConcurrencyStartWorker([
            'mode' => 'auto_suspend',
            'tenant_id' => (int) $tenant->id,
            'now' => '2026-08-05 08:00:00 Asia/Yerevan',
            'start_file' => $startFile,
            'ready_file' => $readyFile,
            'application_name' => 'subscription-auto-race-'.bin2hex(random_bytes(4)),
        ]);

        touch($startFile);
        subscriptionConcurrencyWaitForReadyFile($readyFile, $process);
        usleep(300_000);

        expect($process->isRunning())->toBeTrue();

        app(RecordTenantSubscriptionPayment::class)(
            (int) $tenant->id,
            subscriptionConcurrencyNow('2026-08-05 00:00:00'),
            subscriptionConcurrencyNow('2026-08-01 00:00:00'),
        );

        DB::commit();
    } catch (Throwable $exception) {
        DB::rollBack();

        if (isset($process) && $process->isRunning()) {
            $process->stop(1);
        }

        throw $exception;
    }

    $result = subscriptionConcurrencyWaitFor($process);

    expect($result['ok'])->toBeTrue()
        ->and($result['candidate_count'])->toBe(1)
        ->and($result['suspended_count'])->toBe(0)
        ->and($result['skipped_no_longer_suspendable_count'])->toBe(1)
        ->and($tenant->refresh()->status)->toBe('active')
        ->and(subscriptionConcurrencyNextDueOn($tenant))->toBe('2026-09-01');

    app(TenantResolver::class)->set((int) $tenant->id);
    app(BranchContext::class)->clear();
    expect(AuditLog::query()->where('action', 'tenancy.tenant.suspended')->count())->toBe(0);
});

function subscriptionConcurrencyTenant(string $suffix): Tenant
{
    return Tenant::query()->create([
        'name' => "Subscription Concurrency {$suffix}",
        'slug' => 'subscription-concurrency-'.$suffix.'-'.str()->random(8),
        'default_locale' => 'hy',
        'currency' => 'AMD',
        'status' => 'active',
    ]);
}

function subscriptionConcurrencySubscription(Tenant $tenant, string $nextDueOn, int $graceDays): TenantSubscription
{
    return TenantSubscription::query()->create([
        'tenant_id' => (int) $tenant->id,
        'billing_anchor_day' => (int) (new DateTimeImmutable($nextDueOn))->format('j'),
        'next_due_on' => $nextDueOn,
        'grace_days' => $graceDays,
        'last_paid_on' => null,
    ]);
}

function subscriptionConcurrencyNow(string $dateTime): DateTimeImmutable
{
    return new DateTimeImmutable($dateTime.' Asia/Yerevan');
}

function subscriptionConcurrencySetTimeouts(): void
{
    DB::statement("set lock_timeout = '1500ms'");
    DB::statement("set statement_timeout = '10000ms'");
}

function subscriptionConcurrencyNextDueOn(Tenant $tenant): ?string
{
    $value = TenantSubscription::query()
        ->where('tenant_id', (int) $tenant->id)
        ->value('next_due_on');

    if ($value instanceof DateTimeInterface) {
        return $value->format('Y-m-d');
    }

    return is_string($value) && $value !== '' ? $value : null;
}

/**
 * @param  array<string, mixed>  $payload
 */
function subscriptionConcurrencyStartWorker(array $payload): Process
{
    $encoded = base64_encode(json_encode($payload, JSON_THROW_ON_ERROR));
    $process = new Process([
        PHP_BINARY,
        base_path('tests/Support/Tenancy/concurrent_subscription_worker.php'),
        $encoded,
    ], base_path(), subscriptionConcurrencyProcessEnvironment());
    $process->setTimeout(25);
    $process->start();

    return $process;
}

/**
 * @return array<string, mixed>
 */
function subscriptionConcurrencyWaitFor(Process $process): array
{
    $process->wait();

    if (! $process->isSuccessful()) {
        throw new RuntimeException($process->getErrorOutput() ?: $process->getOutput());
    }

    $lines = array_values(array_filter(explode("\n", trim($process->getOutput()))));
    $lastLine = end($lines);

    if (! is_string($lastLine)) {
        throw new RuntimeException('Subscription concurrency worker produced no output.');
    }

    $result = json_decode($lastLine, true, flags: JSON_THROW_ON_ERROR);

    if (! is_array($result)) {
        throw new RuntimeException('Subscription concurrency worker produced invalid JSON.');
    }

    return $result;
}

function subscriptionConcurrencyWaitForReadyFile(string $readyFile, Process $process): void
{
    $deadline = microtime(true) + 8.0;

    while (microtime(true) <= $deadline) {
        if (! $process->isRunning()) {
            throw new RuntimeException($process->getErrorOutput() ?: $process->getOutput() ?: "Worker exited before creating {$readyFile}.");
        }

        if (file_exists($readyFile)) {
            return;
        }

        usleep(20_000);
    }

    throw new RuntimeException("Timed out waiting for {$readyFile}.");
}

function subscriptionConcurrencyPrefix(string $name): string
{
    $directory = storage_path('framework/testing');

    if (! is_dir($directory)) {
        mkdir($directory, 0775, true);
    }

    return $directory.'/subscription-concurrency-'.$name.'-'.bin2hex(random_bytes(6));
}

/**
 * @return array<string, string>
 */
function subscriptionConcurrencyProcessEnvironment(): array
{
    $connection = config('database.connections.pgsql');

    return [
        'APP_ENV' => 'testing',
        'APP_KEY' => (string) config('app.key'),
        'BCRYPT_ROUNDS' => '4',
        'CACHE_STORE' => 'array',
        'DB_CONNECTION' => 'pgsql',
        'DB_DATABASE' => (string) ($connection['database'] ?? ''),
        'DB_HOST' => (string) ($connection['host'] ?? ''),
        'DB_PASSWORD' => (string) ($connection['password'] ?? ''),
        'DB_PORT' => (string) ($connection['port'] ?? '5432'),
        'DB_URL' => '',
        'DB_USERNAME' => (string) ($connection['username'] ?? ''),
        'QUEUE_CONNECTION' => 'sync',
        'SESSION_DRIVER' => 'array',
    ];
}
