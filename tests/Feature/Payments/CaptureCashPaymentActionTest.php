<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Models\Permission;
use App\Modules\Identity\Infrastructure\Models\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Identity\Infrastructure\Models\UserBranchAssignment;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Payments\Application\CaptureCashPayment;
use App\Modules\Payments\Application\CaptureCashPaymentCommand;
use App\Modules\Payments\Contracts\PaymentPermissions;
use App\Modules\Payments\Domain\PaymentsDomainException;
use App\Modules\Payments\Infrastructure\Models\Cashbox;
use App\Modules\Payments\Infrastructure\Models\CashboxEntry;
use App\Modules\Payments\Infrastructure\Models\Payment;
use App\Modules\Payments\Infrastructure\Models\PaymentAllocation;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Modules\Tenancy\Infrastructure\Models\Branch;
use App\Modules\Tenancy\Infrastructure\Models\Tenant;
use App\Support\Audit\AuditLog;
use App\Support\Audit\AuditRecorder;
use App\Support\Logging\LogContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

afterEach(function (): void {
    auth()->logout();
    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();
    LogContext::clear();
});

it('captures a full cash payment with one payment allocation cashbox entry and audit row', function (): void {
    $fixture = captureCashPaymentFixture();
    captureCashPaymentActingIn($fixture, 'capture-success');

    $result = app(CaptureCashPayment::class)(captureCashPaymentCommand($fixture, 'capture-key'));

    expect($result->paymentId)->toBeInt()
        ->and($result->paymentAllocationId)->toBeInt()
        ->and($result->cashboxEntryId)->toBeInt()
        ->and($result->tenantId)->toBe((int) $fixture['tenant']->id)
        ->and($result->branchId)->toBe((int) $fixture['branch']->id)
        ->and($result->orderId)->toBe((int) $fixture['order']->id)
        ->and($result->cashboxId)->toBe((int) $fixture['cashbox']->id)
        ->and($result->amountMinor)->toBe(6_500)
        ->and($result->currency)->toBe('AMD')
        ->and($result->idempotencyKey)->toBe('capture-key')
        ->and($result->idempotencyFingerprint)->toMatch('/^[a-f0-9]{64}$/')
        ->and($result->replayed)->toBeFalse();

    $payment = Payment::query()->findOrFail($result->paymentId);
    $allocation = PaymentAllocation::query()->findOrFail($result->paymentAllocationId);
    $cashboxEntry = CashboxEntry::query()->findOrFail($result->cashboxEntryId);
    $audit = AuditLog::query()->where('action', 'payments.payment.captured')->sole();

    expect($payment->method)->toBe('cash')
        ->and($payment->status)->toBe('captured')
        ->and($payment->amount_minor)->toBe(6_500)
        ->and($payment->currency)->toBe('AMD')
        ->and($payment->idempotency_key)->toBe('capture-key')
        ->and($allocation->payment_id)->toBe($payment->id)
        ->and($allocation->payable_type)->toBe('order')
        ->and($allocation->payable_id)->toBe((int) $fixture['order']->id)
        ->and($allocation->amount_minor)->toBe(6_500)
        ->and($cashboxEntry->cashbox_id)->toBe((int) $fixture['cashbox']->id)
        ->and($cashboxEntry->source_type)->toBe('payment')
        ->and($cashboxEntry->source_id)->toBe($payment->id)
        ->and($cashboxEntry->posted_by_id)->toBe((int) $fixture['user']->id)
        ->and($audit->target_type)->toBe('payments_payment')
        ->and($audit->target_id)->toBe($payment->id)
        ->and($audit->actor_id)->toBe((int) $fixture['user']->id)
        ->and($audit->branch_id)->toBe((int) $fixture['branch']->id)
        ->and($audit->after_json['payment_id'])->toBe($payment->id)
        ->and($audit->after_json['cashbox_entry_id'])->toBe($cashboxEntry->id);
});

it('returns the original result for an identical sequential idempotent replay', function (): void {
    $fixture = captureCashPaymentFixture();
    captureCashPaymentActingIn($fixture, 'capture-replay');
    $command = captureCashPaymentCommand($fixture, 'same-key');

    $first = app(CaptureCashPayment::class)($command);
    $second = app(CaptureCashPayment::class)($command);

    expect($second->paymentId)->toBe($first->paymentId)
        ->and($second->paymentAllocationId)->toBe($first->paymentAllocationId)
        ->and($second->cashboxEntryId)->toBe($first->cashboxEntryId)
        ->and($second->idempotencyFingerprint)->toBe($first->idempotencyFingerprint)
        ->and($second->replayed)->toBeTrue()
        ->and(Payment::query()->count())->toBe(1)
        ->and(PaymentAllocation::query()->count())->toBe(1)
        ->and(CashboxEntry::query()->count())->toBe(1)
        ->and(AuditLog::query()->where('action', 'payments.payment.captured')->count())->toBe(1);
});

it('rejects a reused idempotency key with a different capture fingerprint', function (): void {
    $fixture = captureCashPaymentFixture();
    captureCashPaymentActingIn($fixture, 'capture-conflict');

    app(CaptureCashPayment::class)(captureCashPaymentCommand($fixture, 'conflict-key'));

    try {
        app(CaptureCashPayment::class)(new CaptureCashPaymentCommand(
            orderId: (int) $fixture['order']->id,
            cashboxId: (int) $fixture['cashbox']->id,
            expectedAmountMinor: 6_499,
            expectedCurrency: 'AMD',
            idempotencyKey: 'conflict-key',
        ));
        $this->fail('Expected idempotency conflict.');
    } catch (PaymentsDomainException $exception) {
        expect($exception->errorCode())->toBe('payments.idempotency_conflict');
    }

    expect(Payment::query()->count())->toBe(1)
        ->and(PaymentAllocation::query()->count())->toBe(1)
        ->and(CashboxEntry::query()->count())->toBe(1)
        ->and(AuditLog::query()->where('action', 'payments.payment.captured')->count())->toBe(1);
});

it('rejects payment capture without the payments capture permission', function (): void {
    $fixture = captureCashPaymentFixture(grantCapture: false);
    captureCashPaymentActingIn($fixture, 'capture-denied');

    expect(fn () => app(CaptureCashPayment::class)(captureCashPaymentCommand($fixture, 'denied-key')))
        ->toThrow(AuthorizationException::class)
        ->and(Payment::query()->count())->toBe(0)
        ->and(PaymentAllocation::query()->count())->toBe(0)
        ->and(CashboxEntry::query()->count())->toBe(0)
        ->and(AuditLog::query()->where('action', 'payments.payment.captured')->count())->toBe(0);
});

it('rolls back financial facts when the transaction bound audit row fails', function (): void {
    $fixture = captureCashPaymentFixture();
    captureCashPaymentActingIn($fixture, 'capture-audit-fails');

    app()->instance(AuditRecorder::class, new class implements AuditRecorder
    {
        public function record(string $action, string $targetType, int $targetId, ?array $before = null, ?array $after = null): AuditLog
        {
            throw new RuntimeException('Audit write failed.');
        }
    });

    expect(fn () => app(CaptureCashPayment::class)(captureCashPaymentCommand($fixture, 'audit-fails-key')))
        ->toThrow(RuntimeException::class, 'Audit write failed.')
        ->and(Payment::query()->count())->toBe(0)
        ->and(PaymentAllocation::query()->count())->toBe(0)
        ->and(CashboxEntry::query()->count())->toBe(0)
        ->and(AuditLog::query()->where('action', 'payments.payment.captured')->count())->toBe(0);
});

it('does not close or otherwise mutate the order workflow state', function (): void {
    $fixture = captureCashPaymentFixture();
    captureCashPaymentActingIn($fixture, 'capture-order-unchanged');
    $before = (array) DB::table('orders')->where('id', (int) $fixture['order']->id)->first();

    app(CaptureCashPayment::class)(captureCashPaymentCommand($fixture, 'order-unchanged-key'));

    $after = (array) DB::table('orders')->where('id', (int) $fixture['order']->id)->first();

    expect($after)->toBe($before);
});

/**
 * @return array{tenant: Tenant, branch: Branch, user: User, order: Order, cashbox: Cashbox}
 */
function captureCashPaymentFixture(bool $grantCapture = true): array
{
    $tenant = Tenant::query()->create([
        'name' => 'Capture Cash Tenant',
        'slug' => 'capture-cash-'.str()->random(8),
        'default_locale' => 'en',
        'currency' => 'AMD',
        'status' => 'active',
    ]);

    app(TenantResolver::class)->set((int) $tenant->id);

    $branch = Branch::query()->create([
        'name' => 'Capture Cash Branch',
        'timezone' => 'Asia/Yerevan',
        'status' => 'active',
    ]);
    app(BranchContext::class)->set((int) $branch->id);

    $role = Role::query()->create([
        'code' => 'cashier',
        'name' => 'Cashier',
    ]);

    if ($grantCapture) {
        $permission = Permission::query()->create([
            'code' => PaymentPermissions::CAPTURE,
            'name' => 'Capture payments',
        ]);
        $role->permissions()->attach((int) $permission->id, ['tenant_id' => (int) $tenant->id]);
    }

    $user = User::query()->create([
        'role_id' => (int) $role->id,
        'name' => 'Capture Cashier',
        'email' => 'capture-cashier-'.str()->random(8).'@smartrest.test',
        'username' => 'capture-cashier-'.str()->random(8),
        'default_locale' => 'en',
        'active' => true,
        'is_superadmin' => false,
        'password' => Hash::make('password'),
    ]);

    UserBranchAssignment::query()->create([
        'user_id' => (int) $user->id,
        'branch_id' => (int) $branch->id,
    ]);

    $order = Order::query()->create([
        'branch_id' => (int) $branch->id,
        'type' => 'fast_food',
        'status' => 'open',
        'table_id' => null,
        'opened_at' => now(),
        'closed_at' => null,
        'client_count' => 1,
        'subtotal_minor' => 6_500,
        'discount_minor' => 0,
        'total_minor' => 6_500,
        'currency' => 'AMD',
    ]);

    $cashbox = Cashbox::query()->create([
        'branch_id' => (int) $branch->id,
        'name' => 'Main Register',
        'is_active' => true,
        'is_default' => true,
    ]);

    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();

    return [
        'tenant' => $tenant,
        'branch' => $branch,
        'user' => $user,
        'order' => $order,
        'cashbox' => $cashbox,
    ];
}

/**
 * @param  array{tenant: Tenant, branch: Branch, user: User, order: Order, cashbox: Cashbox}  $fixture
 */
function captureCashPaymentActingIn(array $fixture, string $requestId): void
{
    app(TenantResolver::class)->set((int) $fixture['tenant']->id);
    app(BranchContext::class)->set((int) $fixture['branch']->id);
    auth()->login($fixture['user']);
    LogContext::start($requestId, 'payments');
}

/**
 * @param  array{order: Order, cashbox: Cashbox}  $fixture
 */
function captureCashPaymentCommand(array $fixture, string $idempotencyKey): CaptureCashPaymentCommand
{
    return new CaptureCashPaymentCommand(
        orderId: (int) $fixture['order']->id,
        cashboxId: (int) $fixture['cashbox']->id,
        expectedAmountMinor: 6_500,
        expectedCurrency: 'AMD',
        idempotencyKey: $idempotencyKey,
    );
}
