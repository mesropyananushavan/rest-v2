<?php

declare(strict_types=1);

use App\Modules\Identity\Contracts\Authorizer;
use App\Modules\Identity\Infrastructure\Models\Permission;
use App\Modules\Identity\Infrastructure\Models\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Identity\Infrastructure\Models\UserBranchAssignment;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Payments\Application\CaptureCashPayment;
use App\Modules\Payments\Application\CaptureCashPaymentCommand;
use App\Modules\Payments\Contracts\PaymentPermissions;
use App\Modules\Payments\Infrastructure\Models\Cashbox;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Modules\Tenancy\Infrastructure\Models\Branch;
use App\Modules\Tenancy\Infrastructure\Models\Tenant;
use App\Support\Audit\AuditLog;
use App\Support\Audit\AuditRecorder;
use App\Support\Logging\LogContext;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\Process\Process;
use Tests\Support\Payments\ConcurrentCaptureCashPaymentWorker;

beforeEach(function (): void {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('CaptureCashPayment PostgreSQL verification runs only on PostgreSQL.');
    }

    Artisan::call('migrate:fresh', ['--force' => true]);
    paymentPgSetTimeouts();
});

afterEach(function (): void {
    auth()->logout();
    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();
    LogContext::clear();
    DB::purge('payment_pg_probe');
});

it('enforces forced RLS policies and direct PostgreSQL protections for financial tables', function (): void {
    expect(paymentPgForcedRls())->toBe([
        'cashbox_entries' => true,
        'payment_allocations' => true,
        'payments' => true,
    ])->and(paymentPgPolicyNames())->toBe([
        'cashbox_entries_tenant_isolation',
        'payment_allocations_tenant_isolation',
        'payments_tenant_isolation',
    ])->and(paymentPgTriggerNames())->toBe([
        'cashbox_entries_insert_consistency',
        'cashbox_entries_no_delete',
        'cashbox_entries_no_update',
        'payment_allocations_insert_consistency',
        'payment_allocations_no_delete',
        'payment_allocations_no_update',
        'payments_insert_consistency',
        'payments_no_delete',
        'payments_no_update',
    ]);

    $tenantA = paymentPgFixture('rls-a');
    $tenantB = paymentPgFixture('rls-b');
    paymentPgActingIn($tenantA, 'payment-pg-rls-a');
    $resultA = app(CaptureCashPayment::class)(paymentPgCommand($tenantA, 'rls-key-a'));
    paymentPgActingIn($tenantB, 'payment-pg-rls-b');
    $resultB = app(CaptureCashPayment::class)(paymentPgCommand($tenantB, 'rls-key-b'));

    app(TenantResolver::class)->clear();

    expect(DB::table('payments')->pluck('id')->all())->toBe([])
        ->and(DB::table('payment_allocations')->pluck('id')->all())->toBe([])
        ->and(DB::table('cashbox_entries')->pluck('id')->all())->toBe([]);

    app(TenantResolver::class)->set($tenantA['tenant_id']);

    expect(DB::table('payments')->pluck('id')->all())->toBe([$resultA->paymentId])
        ->and(DB::table('payment_allocations')->pluck('id')->all())->toBe([$resultA->paymentAllocationId])
        ->and(DB::table('cashbox_entries')->pluck('id')->all())->toBe([$resultA->cashboxEntryId])
        ->and(DB::table('payments')->where('id', $resultB->paymentId)->count())->toBe(0);

    expect(fn (): bool => DB::insert(
        'insert into payments (tenant_id, branch_id, order_id, cashbox_id, method, status, amount_minor, currency, idempotency_key, idempotency_fingerprint, created_at, updated_at) values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$tenantB['tenant_id'], $tenantB['branch_id'], $tenantB['order_id'], $tenantB['cashbox_id'], 'cash', 'captured', 6500, 'AMD', 'forged-tenant-payment', hash('sha256', 'forged-tenant-payment'), now(), now()],
    ))->toThrow(QueryException::class);

    app(TenantResolver::class)->clear();

    expect(fn (): bool => DB::insert(
        'insert into payments (tenant_id, branch_id, order_id, cashbox_id, method, status, amount_minor, currency, idempotency_key, idempotency_fingerprint, created_at, updated_at) values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$tenantA['tenant_id'], $tenantA['branch_id'], $tenantA['order_id'], $tenantA['cashbox_id'], 'cash', 'captured', 6500, 'AMD', 'missing-context-payment', hash('sha256', 'missing-context-payment'), now(), now()],
    ))->toThrow(QueryException::class);

    paymentPgActingIn($tenantA, 'payment-pg-append-only');

    foreach ([
        'payments update' => fn (): int => DB::table('payments')->where('id', $resultA->paymentId)->update(['status' => 'captured']),
        'payments delete' => fn (): int => DB::table('payments')->where('id', $resultA->paymentId)->delete(),
        'payment_allocations update' => fn (): int => DB::table('payment_allocations')->where('id', $resultA->paymentAllocationId)->update(['amount_minor' => 1]),
        'payment_allocations delete' => fn (): int => DB::table('payment_allocations')->where('id', $resultA->paymentAllocationId)->delete(),
        'cashbox_entries update' => fn (): int => DB::table('cashbox_entries')->where('id', $resultA->cashboxEntryId)->update(['amount_minor' => 1]),
        'cashbox_entries delete' => fn (): int => DB::table('cashbox_entries')->where('id', $resultA->cashboxEntryId)->delete(),
    ] as $case => $operation) {
        expect($operation, $case)->toThrow(QueryException::class);
    }
});

it('rejects invalid financial insert relationships through PostgreSQL consistency triggers', function (): void {
    $record = paymentPgFixture('trigger-a');
    $foreign = paymentPgFixture('trigger-b');
    $otherBranch = paymentPgBranch($record['tenant'], 'Trigger Other Branch');
    $otherBranchCashbox = paymentPgCashbox($otherBranch, 'Trigger Other Cashbox');
    paymentPgActingIn($record, 'payment-pg-triggers');

    expect(fn (): bool => DB::insert(
        'insert into payments (tenant_id, branch_id, order_id, cashbox_id, method, status, amount_minor, currency, idempotency_key, idempotency_fingerprint, created_at, updated_at) values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$record['tenant_id'], $record['branch_id'], $record['order_id'], (int) $otherBranchCashbox->id, 'cash', 'captured', 6500, 'AMD', 'wrong-branch-cashbox', hash('sha256', 'wrong-branch-cashbox'), now(), now()],
    ))->toThrow(QueryException::class);

    $paymentId = paymentPgInsertPayment($record, 'trigger-valid-payment');

    expect(fn (): bool => DB::insert(
        'insert into payment_allocations (tenant_id, branch_id, payment_id, payable_type, payable_id, amount_minor, currency, created_at, updated_at) values (?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$record['tenant_id'], $record['branch_id'], $paymentId, 'order', $foreign['order_id'], 6500, 'AMD', now(), now()],
    ))->toThrow(QueryException::class);

    expect(fn (): bool => DB::insert(
        'insert into cashbox_entries (tenant_id, branch_id, cashbox_id, direction, amount_minor, currency, reason, source_type, source_id, posted_by_id, created_at, updated_at) values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$record['tenant_id'], $record['branch_id'], $record['cashbox_id'], 'in', 6500, 'AMD', 'cash_payment', 'payment', $paymentId, $foreign['user_id'], now(), now()],
    ))->toThrow(QueryException::class);
});

it('runs PostgreSQL capture atomically and rolls back audit or persistence failures', function (): void {
    $record = paymentPgFixture('atomic-success');
    paymentPgActingIn($record, 'payment-pg-atomic-success');

    $result = app(CaptureCashPayment::class)(paymentPgCommand($record, 'atomic-success-key'));

    paymentPgAssertCaptureFacts($record, $result->paymentId, 'atomic-success-key');
    expect($result->amountMinor)->toBe(6500)
        ->and($result->replayed)->toBeFalse()
        ->and(paymentPgOrderWorkflow($record))->toMatchArray([
            'status' => 'open',
            'closed_at' => null,
            'total_minor' => 6500,
        ]);

    $auditFailure = paymentPgFixture('atomic-audit-failure');
    paymentPgActingIn($auditFailure, 'payment-pg-atomic-audit-failure');
    app()->instance(AuditRecorder::class, new class implements AuditRecorder
    {
        public function record(string $action, string $targetType, int $targetId, ?array $before = null, ?array $after = null): AuditLog
        {
            throw new RuntimeException('FCPF6 audit failure.');
        }
    });

    expect(fn (): mixed => app(CaptureCashPayment::class)(paymentPgCommand($auditFailure, 'audit-failure-key')))
        ->toThrow(RuntimeException::class, 'FCPF6 audit failure.');
    paymentPgAssertNoFacts($auditFailure);

    $persistenceFailure = paymentPgFixture('atomic-persistence-failure');
    paymentPgActingIn($persistenceFailure, 'payment-pg-atomic-persistence-failure');
    app()->instance(Authorizer::class, new class implements Authorizer
    {
        public function allows(Authenticatable $user, string $permission): bool
        {
            return $permission === PaymentPermissions::CAPTURE;
        }
    });
    Auth::guard()->setUser(new class($persistenceFailure['tenant_id']) implements Authenticatable
    {
        public function __construct(public int $tenant_id) {}

        public function getAuthIdentifierName(): string
        {
            return 'id';
        }

        public function getAuthIdentifier(): int
        {
            return 999999;
        }

        public function getAuthPasswordName(): string
        {
            return 'password';
        }

        public function getAuthPassword(): string
        {
            return 'unused';
        }

        public function getRememberToken(): ?string
        {
            return null;
        }

        public function setRememberToken($value): void {}

        public function getRememberTokenName(): string
        {
            return 'remember_token';
        }
    });

    expect(fn (): mixed => app(CaptureCashPayment::class)(paymentPgCommand($persistenceFailure, 'persistence-failure-key')))
        ->toThrow(QueryException::class);
    paymentPgAssertNoFacts($persistenceFailure);
});

it('locks the payable order before the selected cashbox and avoids broad cashbox locks', function (): void {
    $record = paymentPgFixture('lock-order-before-cashbox');
    $otherCashbox = paymentPgCashbox($record['branch'], 'Unselected Register', isDefault: false);
    paymentPgActingIn($record, 'payment-pg-lock-order-before-cashbox');
    $prefix = paymentPgPrefix('lock-order-before-cashbox');
    $startFile = "{$prefix}.start";
    $backendPidFile = "{$prefix}.pid";

    DB::beginTransaction();

    try {
        $parentBackendPid = (int) DB::scalar('select pg_backend_pid()');
        DB::statement('select id from orders where id = ? for update', [$record['order_id']]);

        $worker = paymentPgStartWorker(paymentPgPayload($record, $startFile, [
            'mode' => 'capture_cash_payment',
            'idempotency_key' => 'lock-order-key',
            'backend_pid_file' => $backendPidFile,
        ]));
        $workerBackendPid = paymentPgWaitForBackendPid($backendPidFile);
        touch($startFile);
        paymentPgWaitUntilBlockedBy($workerBackendPid, $parentBackendPid);

        DB::statement('select id from cashboxes where id = ? for update', [$record['cashbox_id']]);
        paymentPgProbeSelectCashboxForUpdate($record['tenant_id'], (int) $otherCashbox->id);

        expect(paymentPgWorkerAdvisoryLockCount($workerBackendPid))->toBe(0);

        DB::commit();
    } catch (Throwable $exception) {
        DB::rollBack();

        throw $exception;
    }

    $result = paymentPgWaitFor($worker);
    paymentPgAssertWorkerOk($result);
    paymentPgAssertCaptureCounts($record, 1);
});

it('serializes real concurrent captures through PostgreSQL idempotency and order locks', function (): void {
    $identical = paymentPgFixture('race-identical');
    $identicalResults = paymentPgRunConcurrentCaptures($identical, [
        ['idempotency_key' => 'same-key'],
        ['idempotency_key' => 'same-key'],
    ]);

    expect(paymentPgCountSuccesses($identicalResults))->toBe(2)
        ->and(paymentPgCountReplays($identicalResults))->toBe(1)
        ->and(paymentPgCommittedPaymentIds($identicalResults))->toHaveCount(1);
    paymentPgAssertCaptureCounts($identical, 1);

    $differentKeys = paymentPgFixture('race-different-keys');
    $differentResults = paymentPgRunConcurrentCaptures($differentKeys, [
        ['idempotency_key' => 'different-key-a'],
        ['idempotency_key' => 'different-key-b'],
    ]);

    expect(paymentPgCountSuccesses($differentResults))->toBe(1)
        ->and(paymentPgCountDomainCode($differentResults, 'payments.order_already_fully_paid'))->toBe(1);
    paymentPgAssertCaptureCounts($differentKeys, 1);

    $uniqueRace = paymentPgFixture('race-unique-a');
    $secondOrder = paymentPgOrder($uniqueRace['branch'], 'race-unique-b', totalMinor: 6500);
    $uniqueResults = paymentPgRunConcurrentCaptures($uniqueRace, [
        ['idempotency_key' => 'unique-race-key'],
        ['idempotency_key' => 'unique-race-key', 'order_id' => (int) $secondOrder->id],
    ]);

    expect(paymentPgCountSuccesses($uniqueResults))->toBe(1)
        ->and(paymentPgCountDomainCode($uniqueResults, 'payments.idempotency_conflict'))->toBe(1)
        ->and(paymentPgWorkerTransactionLevels($uniqueResults))->each->toBe(0);
    paymentPgAssertTenantCaptureTotals($uniqueRace, 1);

    $otherTenantA = paymentPgFixture('race-scope-tenant-a');
    $otherTenantB = paymentPgFixture('race-scope-tenant-b');
    $tenantScopeStartFile = paymentPgPrefix('scope-tenant').'.start';
    $tenantResults = paymentPgRunWorkers([
        paymentPgPayload($otherTenantA, $tenantScopeStartFile, ['mode' => 'capture_cash_payment', 'idempotency_key' => 'scoped-key']),
        paymentPgPayload($otherTenantB, $tenantScopeStartFile, ['mode' => 'capture_cash_payment', 'idempotency_key' => 'scoped-key']),
    ]);
    expect(paymentPgCountSuccesses($tenantResults))->toBe(2)
        ->and(paymentPgCommittedPaymentIds($tenantResults))->toHaveCount(2);

    $otherBranchA = paymentPgFixture('race-scope-branch-a');
    $otherBranch = paymentPgBranch($otherBranchA['tenant'], 'Scope Other Branch');
    $otherBranchOrder = paymentPgOrder($otherBranch, 'scope-other-branch', totalMinor: 6500);
    $otherBranchCashbox = paymentPgCashbox($otherBranch, 'Scope Other Register');
    $branchScopeStartFile = paymentPgPrefix('scope-branch').'.start';
    $branchResults = paymentPgRunWorkers([
        paymentPgPayload($otherBranchA, $branchScopeStartFile, ['mode' => 'capture_cash_payment', 'idempotency_key' => 'branch-scoped-key']),
        [
            'tenant_id' => $otherBranchA['tenant_id'],
            'branch_id' => (int) $otherBranch->id,
            'user_id' => $otherBranchA['user_id'],
            'request_id' => 'payment-pg-worker',
            'start_file' => $branchScopeStartFile,
            'mode' => 'capture_cash_payment',
            'order_id' => (int) $otherBranchOrder->id,
            'cashbox_id' => (int) $otherBranchCashbox->id,
            'expected_amount_minor' => 6500,
            'expected_currency' => 'AMD',
            'idempotency_key' => 'branch-scoped-key',
        ],
    ]);
    expect(paymentPgCountSuccesses($branchResults))->toBe(2)
        ->and(paymentPgCommittedPaymentIds($branchResults))->toHaveCount(2);
});

it('coordinates capture against order mutation cancellation and selected cashbox deactivation', function (): void {
    $mutation = paymentPgFixture('coord-order-mutation', totalMinor: 1000);
    $mutationResult = paymentPgRunCaptureBlockedOnOrder($mutation, 'mutation-key', function (array $record): void {
        DB::statement('update orders set subtotal_minor = 2000, total_minor = 2000, updated_at = now() where id = ?', [$record['order_id']]);
    });

    expect($mutationResult['ok'])->toBeFalse()
        ->and($mutationResult['domain_code'])->toBe('payments.expected_amount_mismatch');
    paymentPgAssertNoFacts($mutation);

    $cancellation = paymentPgFixture('coord-order-cancel', totalMinor: 1000);
    $cancellationResult = paymentPgRunCaptureBlockedOnOrder($cancellation, 'cancel-key', function (array $record): void {
        DB::statement("update orders set status = 'cancelled', closed_at = now(), updated_at = now() where id = ?", [$record['order_id']]);
    });

    expect($cancellationResult['ok'])->toBeFalse()
        ->and($cancellationResult['domain_code'])->toBe('orders.order_not_open');
    paymentPgAssertNoFacts($cancellation);

    $cashbox = paymentPgFixture('coord-cashbox-deactivate', totalMinor: 1000);
    paymentPgActingIn($cashbox, 'payment-pg-coord-cashbox');
    $prefix = paymentPgPrefix('coord-cashbox');
    $startFile = "{$prefix}.start";
    $backendPidFile = "{$prefix}.pid";

    DB::beginTransaction();

    try {
        $parentBackendPid = (int) DB::scalar('select pg_backend_pid()');
        DB::statement('select id from cashboxes where id = ? for update', [$cashbox['cashbox_id']]);
        $worker = paymentPgStartWorker(paymentPgPayload($cashbox, $startFile, [
            'mode' => 'capture_cash_payment',
            'idempotency_key' => 'cashbox-deactivation-key',
            'expected_amount_minor' => 1000,
            'backend_pid_file' => $backendPidFile,
        ]));
        $workerBackendPid = paymentPgWaitForBackendPid($backendPidFile);
        touch($startFile);
        paymentPgWaitUntilBlockedBy($workerBackendPid, $parentBackendPid);

        DB::statement('update cashboxes set is_active = false, is_default = false, updated_at = now() where id = ?', [$cashbox['cashbox_id']]);
        DB::commit();
    } catch (Throwable $exception) {
        DB::rollBack();

        throw $exception;
    }

    $cashboxResult = paymentPgWaitFor($worker);

    expect($cashboxResult['ok'])->toBeFalse()
        ->and($cashboxResult['domain_code'])->toBe('payments.cashbox_unavailable');
    paymentPgAssertNoFacts($cashbox);
});

/**
 * @return array{
 *     tenant: Tenant,
 *     tenant_id: int,
 *     branch: Branch,
 *     branch_id: int,
 *     user: User,
 *     user_id: int,
 *     order: Order,
 *     order_id: int,
 *     cashbox: Cashbox,
 *     cashbox_id: int
 * }
 */
function paymentPgFixture(string $suffix, int $totalMinor = 6500): array
{
    $tenant = Tenant::query()->create([
        'name' => "FCPF6 {$suffix} Tenant",
        'slug' => "fcpf6-{$suffix}-".str()->random(6),
        'default_locale' => 'en',
        'currency' => 'AMD',
        'status' => 'active',
    ]);

    app(TenantResolver::class)->set((int) $tenant->id);

    $branch = paymentPgBranch($tenant, "FCPF6 {$suffix} Branch");
    app(BranchContext::class)->set((int) $branch->id);

    $role = Role::query()->create([
        'code' => "fcpf6-{$suffix}-role",
        'name' => "FCPF6 {$suffix} Role",
        'is_management_role' => false,
    ]);
    $permission = Permission::query()->firstOrCreate(
        ['code' => PaymentPermissions::CAPTURE],
        ['name' => 'Capture payments'],
    );
    $role->permissions()->attach((int) $permission->id, ['tenant_id' => (int) $tenant->id]);

    $user = User::query()->create([
        'role_id' => (int) $role->id,
        'name' => "FCPF6 {$suffix} Cashier",
        'email' => "fcpf6-{$suffix}-".str()->random(6).'@smartrest.test',
        'username' => "fcpf6-{$suffix}-".str()->random(6),
        'default_locale' => 'en',
        'active' => true,
        'is_superadmin' => false,
        'password' => Hash::make('password'),
    ]);

    UserBranchAssignment::query()->create([
        'user_id' => (int) $user->id,
        'branch_id' => (int) $branch->id,
    ]);

    $order = paymentPgOrder($branch, "{$suffix}-order", totalMinor: $totalMinor);
    $cashbox = paymentPgCashbox($branch, "FCPF6 {$suffix} Register");

    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();

    return [
        'tenant' => $tenant,
        'tenant_id' => (int) $tenant->id,
        'branch' => $branch,
        'branch_id' => (int) $branch->id,
        'user' => $user,
        'user_id' => (int) $user->id,
        'order' => $order,
        'order_id' => (int) $order->id,
        'cashbox' => $cashbox,
        'cashbox_id' => (int) $cashbox->id,
    ];
}

function paymentPgBranch(Tenant $tenant, string $name): Branch
{
    app(TenantResolver::class)->set((int) $tenant->id);

    return Branch::query()->create([
        'name' => $name,
        'timezone' => 'Asia/Yerevan',
        'status' => 'active',
    ]);
}

function paymentPgOrder(Branch $branch, string $suffix, int $totalMinor = 6500, string $status = 'open'): Order
{
    app(TenantResolver::class)->set((int) $branch->tenant_id);
    app(BranchContext::class)->set((int) $branch->id);

    return Order::query()->create([
        'branch_id' => (int) $branch->id,
        'type' => 'fast_food',
        'status' => $status,
        'table_id' => null,
        'opened_at' => now(),
        'closed_at' => $status === 'open' ? null : now(),
        'client_count' => 1,
        'subtotal_minor' => $totalMinor,
        'discount_minor' => 0,
        'total_minor' => $totalMinor,
        'currency' => 'AMD',
        'comment' => "FCPF6 {$suffix}",
    ]);
}

function paymentPgCashbox(Branch $branch, string $name, bool $isActive = true, bool $isDefault = true): Cashbox
{
    app(TenantResolver::class)->set((int) $branch->tenant_id);
    app(BranchContext::class)->set((int) $branch->id);

    return Cashbox::query()->create([
        'branch_id' => (int) $branch->id,
        'name' => $name,
        'is_active' => $isActive,
        'is_default' => $isDefault,
    ]);
}

/**
 * @param  array{tenant_id: int, branch_id: int, user: User}  $record
 */
function paymentPgActingIn(array $record, string $requestId): void
{
    app(TenantResolver::class)->set($record['tenant_id']);
    app(BranchContext::class)->set($record['branch_id']);
    auth()->login($record['user']);
    LogContext::start($requestId, 'payments');
}

/**
 * @param  array{order_id: int, cashbox_id: int}  $record
 */
function paymentPgCommand(array $record, string $idempotencyKey, ?int $expectedAmountMinor = null): CaptureCashPaymentCommand
{
    return new CaptureCashPaymentCommand(
        orderId: $record['order_id'],
        cashboxId: $record['cashbox_id'],
        expectedAmountMinor: $expectedAmountMinor ?? 6500,
        expectedCurrency: 'AMD',
        idempotencyKey: $idempotencyKey,
    );
}

/**
 * @param  array{tenant_id: int, branch_id: int, order_id: int, cashbox_id: int}  $record
 */
function paymentPgInsertPayment(array $record, string $idempotencyKey, int $amountMinor = 6500): int
{
    return (int) DB::table('payments')->insertGetId([
        'tenant_id' => $record['tenant_id'],
        'branch_id' => $record['branch_id'],
        'order_id' => $record['order_id'],
        'cashbox_id' => $record['cashbox_id'],
        'method' => 'cash',
        'status' => 'captured',
        'amount_minor' => $amountMinor,
        'currency' => 'AMD',
        'idempotency_key' => $idempotencyKey,
        'idempotency_fingerprint' => hash('sha256', $idempotencyKey),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/**
 * @return array<string, bool>
 */
function paymentPgForcedRls(): array
{
    return collect(DB::select(
        "select relname, relforcerowsecurity
         from pg_class
         where relname in ('payments', 'payment_allocations', 'cashbox_entries')
         order by relname",
    ))->mapWithKeys(fn (stdClass $row): array => [(string) $row->relname => (bool) $row->relforcerowsecurity])
        ->all();
}

/**
 * @return list<string>
 */
function paymentPgPolicyNames(): array
{
    return collect(DB::select(
        "select policyname
         from pg_policies
         where tablename in ('payments', 'payment_allocations', 'cashbox_entries')
         order by policyname",
    ))->pluck('policyname')->all();
}

/**
 * @return list<string>
 */
function paymentPgTriggerNames(): array
{
    return collect(DB::select(
        "select tgname
         from pg_trigger
         where tgrelid in ('payments'::regclass, 'payment_allocations'::regclass, 'cashbox_entries'::regclass)
           and not tgisinternal
         order by tgname",
    ))->pluck('tgname')->all();
}

/**
 * @param  array{tenant_id: int, branch_id: int, order_id: int, cashbox_id: int, user_id: int}  $record
 */
function paymentPgAssertCaptureFacts(array $record, int $paymentId, string $idempotencyKey): void
{
    expect(DB::table('payments')->where('id', $paymentId)->where([
        'tenant_id' => $record['tenant_id'],
        'branch_id' => $record['branch_id'],
        'order_id' => $record['order_id'],
        'cashbox_id' => $record['cashbox_id'],
        'method' => 'cash',
        'status' => 'captured',
        'amount_minor' => 6500,
        'currency' => 'AMD',
        'idempotency_key' => $idempotencyKey,
    ])->count())->toBe(1)
        ->and(DB::table('payment_allocations')->where('payment_id', $paymentId)->where([
            'tenant_id' => $record['tenant_id'],
            'branch_id' => $record['branch_id'],
            'payable_type' => 'order',
            'payable_id' => $record['order_id'],
            'amount_minor' => 6500,
            'currency' => 'AMD',
        ])->count())->toBe(1)
        ->and(DB::table('cashbox_entries')->where([
            'tenant_id' => $record['tenant_id'],
            'branch_id' => $record['branch_id'],
            'cashbox_id' => $record['cashbox_id'],
            'direction' => 'in',
            'amount_minor' => 6500,
            'currency' => 'AMD',
            'source_type' => 'payment',
            'source_id' => $paymentId,
            'posted_by_id' => $record['user_id'],
        ])->count())->toBe(1)
        ->and(DB::table('audit_logs')->where([
            'tenant_id' => $record['tenant_id'],
            'branch_id' => $record['branch_id'],
            'actor_id' => $record['user_id'],
            'action' => 'payments.payment.captured',
            'target_type' => 'payments_payment',
            'target_id' => $paymentId,
        ])->count())->toBe(1);
}

/**
 * @param  array{tenant_id: int, branch_id: int, order_id: int}  $record
 * @return array{status: string|null, closed_at: mixed, total_minor: int|null}
 */
function paymentPgOrderWorkflow(array $record): array
{
    $row = DB::table('orders')->where('id', $record['order_id'])->first(['status', 'closed_at', 'total_minor']);

    return [
        'status' => $row?->status,
        'closed_at' => $row?->closed_at,
        'total_minor' => $row === null ? null : (int) $row->total_minor,
    ];
}

/**
 * @param  array{order_id: int}  $record
 */
function paymentPgAssertNoFacts(array $record): void
{
    app(TenantResolver::class)->set($record['tenant_id']);

    expect(DB::table('payments')->where('order_id', $record['order_id'])->count())->toBe(0)
        ->and(DB::table('payment_allocations')->where('payable_id', $record['order_id'])->count())->toBe(0)
        ->and(DB::table('cashbox_entries')->where('source_type', 'payment')->count())->toBe(0)
        ->and(DB::table('audit_logs')->where('action', 'payments.payment.captured')->count())->toBe(0);
}

/**
 * @param  array{order_id: int}  $record
 */
function paymentPgAssertCaptureCounts(array $record, int $expected): void
{
    app(TenantResolver::class)->set($record['tenant_id']);

    expect(DB::table('payments')->where('order_id', $record['order_id'])->count())->toBe($expected)
        ->and(DB::table('payment_allocations')->where('payable_id', $record['order_id'])->count())->toBe($expected)
        ->and(DB::table('cashbox_entries')->where('source_type', 'payment')->count())->toBeGreaterThanOrEqual($expected)
        ->and(DB::table('audit_logs')->where('action', 'payments.payment.captured')->count())->toBeGreaterThanOrEqual($expected);
}

/**
 * @param  array{tenant_id: int, branch_id: int}  $record
 */
function paymentPgAssertTenantCaptureTotals(array $record, int $expected): void
{
    app(TenantResolver::class)->set($record['tenant_id']);

    expect(DB::table('payments')->where('branch_id', $record['branch_id'])->count())->toBe($expected)
        ->and(DB::table('payment_allocations')->where('branch_id', $record['branch_id'])->count())->toBe($expected)
        ->and(DB::table('cashbox_entries')->where('branch_id', $record['branch_id'])->count())->toBe($expected)
        ->and(DB::table('audit_logs')->where('branch_id', $record['branch_id'])->where('action', 'payments.payment.captured')->count())->toBe($expected);
}

function paymentPgSetTimeouts(): void
{
    DB::statement("set lock_timeout = '1500ms'");
    DB::statement("set statement_timeout = '10000ms'");
}

/**
 * @param  array<string, mixed>  $payload
 */
function paymentPgStartWorker(array $payload): Process
{
    $process = new Process(ConcurrentCaptureCashPaymentWorker::command($payload), base_path(), paymentPgProcessEnvironment());
    $process->setTimeout(20);
    $process->start();

    return $process;
}

/**
 * @return array<string, string>
 */
function paymentPgProcessEnvironment(): array
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

/**
 * @return array<string, mixed>
 */
function paymentPgWaitFor(Process $process): array
{
    $process->wait();

    if (! $process->isSuccessful()) {
        throw new RuntimeException($process->getErrorOutput() ?: $process->getOutput());
    }

    $lines = array_values(array_filter(explode("\n", trim($process->getOutput()))));
    $lastLine = end($lines);

    if (! is_string($lastLine)) {
        throw new RuntimeException('Payment concurrency worker produced no output.');
    }

    $decoded = json_decode($lastLine, true, flags: JSON_THROW_ON_ERROR);

    if (! is_array($decoded)) {
        throw new RuntimeException('Payment concurrency worker produced invalid JSON.');
    }

    return $decoded;
}

/**
 * @param  array<string, mixed>  $record
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function paymentPgPayload(array $record, string $startFile, array $overrides): array
{
    return $overrides + [
        'tenant_id' => $record['tenant_id'],
        'branch_id' => $record['branch_id'],
        'user_id' => $record['user_id'],
        'request_id' => 'payment-pg-worker',
        'start_file' => $startFile,
        'order_id' => $record['order_id'],
        'cashbox_id' => $record['cashbox_id'],
        'expected_amount_minor' => 6500,
        'expected_currency' => 'AMD',
    ];
}

/**
 * @param  array{tenant_id: int, branch_id: int, user_id: int, order_id: int, cashbox_id: int}  $record
 * @param  list<array<string, mixed>>  $overrides
 * @return list<array<string, mixed>>
 */
function paymentPgRunConcurrentCaptures(array $record, array $overrides): array
{
    $startFile = paymentPgPrefix('capture-race').'.start';

    return paymentPgRunWorkers(array_map(
        fn (array $override): array => paymentPgPayload($record, $startFile, ['mode' => 'capture_cash_payment'] + $override),
        $overrides,
    ));
}

/**
 * @param  list<array<string, mixed>>  $payloads
 * @return list<array<string, mixed>>
 */
function paymentPgRunWorkers(array $payloads): array
{
    $workers = array_map(fn (array $payload): Process => paymentPgStartWorker($payload), $payloads);

    foreach (array_unique(array_map(fn (array $payload): string => (string) $payload['start_file'], $payloads)) as $startFile) {
        touch($startFile);
    }

    return array_map(fn (Process $worker): array => paymentPgWaitFor($worker), $workers);
}

function paymentPgPrefix(string $name): string
{
    $directory = storage_path('framework/testing');

    if (! is_dir($directory)) {
        mkdir($directory, 0775, true);
    }

    return $directory.'/payments-capture-'.$name.'-'.bin2hex(random_bytes(6));
}

function paymentPgWaitForFile(string $path): void
{
    $deadline = microtime(true) + 8.0;

    while (! file_exists($path)) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException("Timed out waiting for {$path}.");
        }

        usleep(20_000);
    }
}

function paymentPgWaitForBackendPid(string $path): int
{
    paymentPgWaitForFile($path);

    $pid = trim((string) file_get_contents($path));

    if (! ctype_digit($pid)) {
        throw new RuntimeException("Invalid PostgreSQL backend pid in {$path}.");
    }

    return (int) $pid;
}

function paymentPgWaitUntilBlockedBy(int $blockedPid, int $blockingPid): void
{
    $deadline = microtime(true) + 8.0;

    do {
        $blockingPids = DB::selectOne('select pg_blocking_pids(?) as pids', [$blockedPid]);
        $normalized = trim((string) ($blockingPids?->pids ?? ''), '{}');
        $ids = $normalized === ''
            ? []
            : array_map(static fn (string $pid): int => (int) $pid, explode(',', $normalized));

        if (in_array($blockingPid, $ids, true)) {
            expect(true)->toBeTrue();

            return;
        }

        usleep(20_000);
    } while (microtime(true) <= $deadline);

    throw new RuntimeException("PostgreSQL backend {$blockedPid} was not blocked by {$blockingPid}.");
}

function paymentPgWorkerAdvisoryLockCount(int $backendPid): int
{
    return (int) DB::table('pg_locks')
        ->where('pid', $backendPid)
        ->where('locktype', 'advisory')
        ->count();
}

function paymentPgProbeSelectCashboxForUpdate(int $tenantId, int $cashboxId): void
{
    config(['database.connections.payment_pg_probe' => config('database.connections.pgsql')]);
    DB::purge('payment_pg_probe');
    $connection = DB::connection('payment_pg_probe');
    $connection->statement("set lock_timeout = '300ms'");
    $connection->statement('select set_config(?, ?, false)', ['smartrest.tenant_id', (string) $tenantId]);
    $connection->beginTransaction();

    try {
        $connection->statement('select id from cashboxes where id = ? for update', [$cashboxId]);
        $connection->commit();
    } catch (Throwable $exception) {
        $connection->rollBack();

        throw $exception;
    } finally {
        DB::purge('payment_pg_probe');
    }
}

/**
 * @param  array{tenant_id: int, branch_id: int, order_id: int, cashbox_id: int, order: Order}  $record
 */
function paymentPgRunCaptureBlockedOnOrder(array $record, string $key, Closure $mutation): array
{
    paymentPgActingIn($record, "payment-pg-blocked-{$key}");
    $prefix = paymentPgPrefix($key);
    $startFile = "{$prefix}.start";
    $backendPidFile = "{$prefix}.pid";

    DB::beginTransaction();

    try {
        $parentBackendPid = (int) DB::scalar('select pg_backend_pid()');
        DB::statement('select id from orders where id = ? for update', [$record['order_id']]);
        $worker = paymentPgStartWorker(paymentPgPayload($record, $startFile, [
            'mode' => 'capture_cash_payment',
            'idempotency_key' => $key,
            'expected_amount_minor' => $record['order']->total_minor,
            'backend_pid_file' => $backendPidFile,
        ]));
        $workerBackendPid = paymentPgWaitForBackendPid($backendPidFile);
        touch($startFile);
        paymentPgWaitUntilBlockedBy($workerBackendPid, $parentBackendPid);

        $mutation($record);
        DB::commit();
    } catch (Throwable $exception) {
        DB::rollBack();

        throw $exception;
    }

    return paymentPgWaitFor($worker);
}

/**
 * @param  array<string, mixed>  $result
 */
function paymentPgAssertWorkerOk(array $result): void
{
    if (($result['ok'] ?? false) !== true) {
        throw new RuntimeException('Payment worker failed: '.json_encode($result, JSON_THROW_ON_ERROR));
    }

    expect($result['transaction_level'] ?? null)->toBe(0);
}

/**
 * @param  list<array<string, mixed>>  $results
 */
function paymentPgCountSuccesses(array $results): int
{
    return count(array_filter($results, static fn (array $result): bool => ($result['ok'] ?? false) === true));
}

/**
 * @param  list<array<string, mixed>>  $results
 */
function paymentPgCountReplays(array $results): int
{
    return count(array_filter($results, static fn (array $result): bool => ($result['replayed'] ?? false) === true));
}

/**
 * @param  list<array<string, mixed>>  $results
 * @return list<int>
 */
function paymentPgCommittedPaymentIds(array $results): array
{
    return array_values(array_unique(array_map(
        static fn (array $result): int => (int) ($result['payment_id'] ?? 0),
        array_filter($results, static fn (array $result): bool => ($result['ok'] ?? false) === true),
    )));
}

/**
 * @param  list<array<string, mixed>>  $results
 */
function paymentPgCountDomainCode(array $results, string $code): int
{
    return count(array_filter($results, static fn (array $result): bool => ($result['domain_code'] ?? null) === $code));
}

/**
 * @param  list<array<string, mixed>>  $results
 * @return list<int>
 */
function paymentPgWorkerTransactionLevels(array $results): array
{
    return array_map(static fn (array $result): int => (int) ($result['transaction_level'] ?? -1), $results);
}
