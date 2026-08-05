<?php

declare(strict_types=1);

use App\Modules\Identity\Contracts\Authorizer;
use App\Modules\Identity\Infrastructure\Models\Permission;
use App\Modules\Identity\Infrastructure\Models\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Identity\Infrastructure\Models\UserBranchAssignment;
use App\Modules\Orders\Domain\OrdersDomainException;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Payments\Application\CaptureCashPayment;
use App\Modules\Payments\Application\CaptureCashPaymentCommand;
use App\Modules\Payments\Application\CaptureCashPaymentFingerprint;
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
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

afterEach(function (): void {
    auth()->logout();
    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();
    LogContext::clear();
});

it('persists a complete cash capture result with exact financial facts', function (): void {
    $record = fcpf5Fixture('complete-success');
    fcpf5ActingIn($record, requestId: 'fcpf5-complete-success');

    $command = fcpf5Command($record, 'complete-success-key');
    $result = app(CaptureCashPayment::class)($command);

    $payment = Payment::query()->findOrFail($result->paymentId);
    $allocation = PaymentAllocation::query()->findOrFail($result->paymentAllocationId);
    $entry = CashboxEntry::query()->findOrFail($result->cashboxEntryId);
    $audit = AuditLog::query()->where('action', 'payments.payment.captured')->sole();
    $fingerprint = app(CaptureCashPaymentFingerprint::class)->forCommand($command);

    expect(DB::table('payments')->count())->toBe(1)
        ->and(DB::table('payment_allocations')->count())->toBe(1)
        ->and(DB::table('cashbox_entries')->count())->toBe(1)
        ->and(DB::table('audit_logs')->where('action', 'payments.payment.captured')->count())->toBe(1)
        ->and($result->paymentId)->toBe((int) $payment->id)
        ->and($result->paymentAllocationId)->toBe((int) $allocation->id)
        ->and($result->cashboxEntryId)->toBe((int) $entry->id)
        ->and($result->tenantId)->toBe($record['tenant_id'])
        ->and($result->branchId)->toBe($record['branch_id'])
        ->and($result->orderId)->toBe($record['order_id'])
        ->and($result->cashboxId)->toBe($record['cashbox_id'])
        ->and($result->amountMinor)->toBe(6_500)
        ->and(is_int($result->amountMinor))->toBeTrue()
        ->and($result->currency)->toBe('AMD')
        ->and($result->idempotencyKey)->toBe('complete-success-key')
        ->and($result->idempotencyFingerprint)->toBe($fingerprint)
        ->and($result->replayed)->toBeFalse()
        ->and((int) $payment->tenant_id)->toBe($record['tenant_id'])
        ->and((int) $payment->branch_id)->toBe($record['branch_id'])
        ->and((int) $payment->order_id)->toBe($record['order_id'])
        ->and((int) $payment->cashbox_id)->toBe($record['cashbox_id'])
        ->and($payment->method)->toBe('cash')
        ->and($payment->status)->toBe('captured')
        ->and($payment->amount_minor)->toBe(6_500)
        ->and(is_int($payment->amount_minor))->toBeTrue()
        ->and($payment->currency)->toBe('AMD')
        ->and($payment->idempotency_key)->toBe('complete-success-key')
        ->and($payment->idempotency_fingerprint)->toBe($fingerprint)
        ->and((int) $allocation->tenant_id)->toBe($record['tenant_id'])
        ->and((int) $allocation->branch_id)->toBe($record['branch_id'])
        ->and((int) $allocation->payment_id)->toBe((int) $payment->id)
        ->and($allocation->payable_type)->toBe('order')
        ->and((int) $allocation->payable_id)->toBe($record['order_id'])
        ->and($allocation->amount_minor)->toBe(6_500)
        ->and(is_int($allocation->amount_minor))->toBeTrue()
        ->and($allocation->currency)->toBe('AMD')
        ->and((int) $entry->tenant_id)->toBe($record['tenant_id'])
        ->and((int) $entry->branch_id)->toBe($record['branch_id'])
        ->and((int) $entry->cashbox_id)->toBe($record['cashbox_id'])
        ->and($entry->direction)->toBe('in')
        ->and($entry->amount_minor)->toBe(6_500)
        ->and(is_int($entry->amount_minor))->toBeTrue()
        ->and($entry->currency)->toBe('AMD')
        ->and($entry->reason)->toBe('cash_payment')
        ->and($entry->source_type)->toBe('payment')
        ->and((int) $entry->source_id)->toBe((int) $payment->id)
        ->and((int) $entry->posted_by_id)->toBe($record['user_id'])
        ->and((int) $audit->tenant_id)->toBe($record['tenant_id'])
        ->and($audit->branch_id)->toBe($record['branch_id'])
        ->and($audit->actor_id)->toBe($record['user_id'])
        ->and($audit->target_type)->toBe('payments_payment')
        ->and($audit->target_id)->toBe((int) $payment->id)
        ->and($audit->correlation_id)->toBe('fcpf5-complete-success')
        ->and($audit->before_json)->toBeNull()
        ->and($audit->after_json['payment_id'])->toBe((int) $payment->id)
        ->and($audit->after_json['payment_allocation_id'])->toBe((int) $allocation->id)
        ->and($audit->after_json['cashbox_entry_id'])->toBe((int) $entry->id)
        ->and($audit->after_json['branch_id'])->toBe($record['branch_id'])
        ->and($audit->after_json['order_id'])->toBe($record['order_id'])
        ->and($audit->after_json['cashbox_id'])->toBe($record['cashbox_id'])
        ->and($audit->after_json['method'])->toBe('cash')
        ->and($audit->after_json['status'])->toBe('captured')
        ->and($audit->after_json['amount_minor'])->toBe(6_500)
        ->and($audit->after_json['currency'])->toBe('AMD')
        ->and($audit->after_json['idempotency_key'])->toBe('complete-success-key')
        ->and($audit->after_json['idempotency_fingerprint'])->toBe($fingerprint);
});

it('keeps capture-created financial facts append only', function (): void {
    $record = fcpf5Fixture('capture-append-only');
    fcpf5ActingIn($record, requestId: 'fcpf5-capture-append-only');

    $result = app(CaptureCashPayment::class)(fcpf5Command($record, 'capture-append-only-key'));

    expect(fn () => tap(Payment::query()->findOrFail($result->paymentId))->forceFill(['status' => 'compromised'])->save())
        ->toThrow(LogicException::class, 'Payments are append-only and cannot be updated.')
        ->and(fn () => tap(PaymentAllocation::query()->findOrFail($result->paymentAllocationId))->forceFill(['amount_minor' => 1])->save())
        ->toThrow(LogicException::class, 'Payment allocations are append-only and cannot be updated.')
        ->and(fn () => tap(CashboxEntry::query()->findOrFail($result->cashboxEntryId))->forceFill(['amount_minor' => 1])->save())
        ->toThrow(LogicException::class, 'Cashbox entries are append-only and cannot be updated.')
        ->and(fn () => Payment::query()->findOrFail($result->paymentId)->delete())
        ->toThrow(LogicException::class, 'Payments are append-only and cannot be deleted.')
        ->and(fn () => PaymentAllocation::query()->findOrFail($result->paymentAllocationId)->delete())
        ->toThrow(LogicException::class, 'Payment allocations are append-only and cannot be deleted.')
        ->and(fn () => CashboxEntry::query()->findOrFail($result->cashboxEntryId)->delete())
        ->toThrow(LogicException::class, 'Cashbox entries are append-only and cannot be deleted.')
        ->and(fn () => Payment::query()->findOrFail($result->paymentId)->forceDelete())
        ->toThrow(LogicException::class, 'Payments are append-only and cannot be deleted.')
        ->and(fn () => PaymentAllocation::query()->findOrFail($result->paymentAllocationId)->forceDelete())
        ->toThrow(LogicException::class, 'Payment allocations are append-only and cannot be deleted.')
        ->and(fn () => CashboxEntry::query()->findOrFail($result->cashboxEntryId)->forceDelete())
        ->toThrow(LogicException::class, 'Cashbox entries are append-only and cannot be deleted.');
});

it('enforces authorization and actor context inside the application action', function (): void {
    $authorized = fcpf5Fixture('effective-permission');
    fcpf5ActingIn($authorized, requestId: 'fcpf5-effective-permission');

    app(CaptureCashPayment::class)(fcpf5Command($authorized, 'effective-permission-key'));

    $superadmin = fcpf5Fixture('superadmin', grantCapture: false, superadmin: true);
    fcpf5ActingIn($superadmin, requestId: 'fcpf5-superadmin');

    $superadminResult = app(CaptureCashPayment::class)(fcpf5Command($superadmin, 'superadmin-key'));

    expect($superadminResult->paymentId)->toBeInt();

    foreach ([
        'missing actor' => function (): void {
            $record = fcpf5Fixture('missing-actor');
            app(TenantResolver::class)->set($record['tenant_id']);
            app(BranchContext::class)->set($record['branch_id']);
            LogContext::start('fcpf5-missing-actor', 'payments');

            fcpf5ExpectPaymentDomainCode(
                fn (): mixed => app(CaptureCashPayment::class)(fcpf5Command($record, 'missing-actor-key')),
                'payments.actor_context_required',
            );
        },
        'foreign actor tenant' => function (): void {
            $record = fcpf5Fixture('foreign-actor-a');
            $foreign = fcpf5Fixture('foreign-actor-b');
            fcpf5ActingIn($record, $foreign['user'], requestId: 'fcpf5-foreign-actor');

            fcpf5ExpectPaymentDomainCode(
                fn (): mixed => app(CaptureCashPayment::class)(fcpf5Command($record, 'foreign-actor-key')),
                'payments.actor_context_required',
            );
        },
        'inactive actor' => function (): void {
            $record = fcpf5Fixture('inactive-actor', activeUser: false);
            fcpf5ActingIn($record, requestId: 'fcpf5-inactive-actor');

            expect(fn () => app(CaptureCashPayment::class)(fcpf5Command($record, 'inactive-actor-key')))
                ->toThrow(AuthorizationException::class);
        },
        'missing permission' => function (): void {
            $record = fcpf5Fixture('missing-permission', grantCapture: false);
            fcpf5ActingIn($record, requestId: 'fcpf5-missing-permission');

            expect(fn () => app(CaptureCashPayment::class)(fcpf5Command($record, 'missing-permission-key')))
                ->toThrow(AuthorizationException::class);
        },
    ] as $case => $assertion) {
        $before = fcpf5FinancialCounts();
        $assertion();
        fcpf5ExpectFinancialCounts($before, $case);
    }
});

it('rejects missing tenant and branch contexts with stable payment errors', function (): void {
    $record = fcpf5Fixture('missing-context');

    app(BranchContext::class)->set($record['branch_id']);
    auth()->login($record['user']);
    LogContext::start('fcpf5-missing-tenant', 'payments');

    fcpf5ExpectPaymentDomainCode(
        fn (): mixed => app(CaptureCashPayment::class)(fcpf5Command($record, 'missing-tenant-key')),
        'payments.tenant_context_required',
        fcpf5FinancialCounts(),
    );

    app(TenantResolver::class)->set($record['tenant_id']);
    app(BranchContext::class)->clear();
    LogContext::start('fcpf5-missing-branch', 'payments');

    fcpf5ExpectPaymentDomainCode(
        fn (): mixed => app(CaptureCashPayment::class)(fcpf5Command($record, 'missing-branch-key')),
        'payments.branch_context_required',
        fcpf5FinancialCounts(),
    );
});

it('validates command values without trimming normalizing or accepting invalid money and currency', function (): void {
    $record = fcpf5Fixture('command-validation');
    fcpf5ActingIn($record, requestId: 'fcpf5-command-validation');

    foreach ([
        'zero order id' => [
            new CaptureCashPaymentCommand(0, $record['cashbox_id'], 6_500, 'AMD', 'invalid-order-id'),
            ModelNotFoundException::class,
            null,
        ],
        'negative order id' => [
            new CaptureCashPaymentCommand(-1, $record['cashbox_id'], 6_500, 'AMD', 'negative-order-id'),
            ModelNotFoundException::class,
            null,
        ],
        'zero cashbox id' => [
            new CaptureCashPaymentCommand($record['order_id'], 0, 6_500, 'AMD', 'invalid-cashbox-id'),
            ModelNotFoundException::class,
            null,
        ],
        'negative cashbox id' => [
            new CaptureCashPaymentCommand($record['order_id'], -1, 6_500, 'AMD', 'negative-cashbox-id'),
            ModelNotFoundException::class,
            null,
        ],
        'zero amount' => [
            new CaptureCashPaymentCommand($record['order_id'], $record['cashbox_id'], 0, 'AMD', 'zero-amount'),
            PaymentsDomainException::class,
            'payments.capture_amount_must_be_positive',
        ],
        'negative amount' => [
            new CaptureCashPaymentCommand($record['order_id'], $record['cashbox_id'], -1, 'AMD', 'negative-amount'),
            PaymentsDomainException::class,
            'payments.capture_amount_must_be_positive',
        ],
        'lowercase currency' => [
            new CaptureCashPaymentCommand($record['order_id'], $record['cashbox_id'], 6_500, 'amd', 'lowercase-currency'),
            PaymentsDomainException::class,
            'payments.capture_currency_invalid',
        ],
        'short currency' => [
            new CaptureCashPaymentCommand($record['order_id'], $record['cashbox_id'], 6_500, 'AM', 'short-currency'),
            PaymentsDomainException::class,
            'payments.capture_currency_invalid',
        ],
        'numeric currency' => [
            new CaptureCashPaymentCommand($record['order_id'], $record['cashbox_id'], 6_500, 'AM1', 'numeric-currency'),
            PaymentsDomainException::class,
            'payments.capture_currency_invalid',
        ],
        'empty idempotency key' => [
            new CaptureCashPaymentCommand($record['order_id'], $record['cashbox_id'], 6_500, 'AMD', ''),
            PaymentsDomainException::class,
            'payments.idempotency_key_required',
        ],
        'overlong idempotency key' => [
            new CaptureCashPaymentCommand($record['order_id'], $record['cashbox_id'], 6_500, 'AMD', str_repeat('k', 129)),
            PaymentsDomainException::class,
            'payments.idempotency_key_too_long',
        ],
        'leading whitespace idempotency key' => [
            new CaptureCashPaymentCommand($record['order_id'], $record['cashbox_id'], 6_500, 'AMD', ' leading-key'),
            PaymentsDomainException::class,
            'payments.idempotency_key_whitespace',
        ],
        'trailing whitespace idempotency key' => [
            new CaptureCashPaymentCommand($record['order_id'], $record['cashbox_id'], 6_500, 'AMD', 'trailing-key '),
            PaymentsDomainException::class,
            'payments.idempotency_key_whitespace',
        ],
        'control character idempotency key' => [
            new CaptureCashPaymentCommand($record['order_id'], $record['cashbox_id'], 6_500, 'AMD', "control\nkey"),
            PaymentsDomainException::class,
            'payments.idempotency_key_control_characters',
        ],
    ] as $case => [$command, $exceptionClass, $errorCode]) {
        $before = fcpf5FinancialCounts();

        if ($exceptionClass === PaymentsDomainException::class) {
            fcpf5ExpectPaymentDomainCode(
                fn (): mixed => app(CaptureCashPayment::class)($command),
                $errorCode,
            );
            fcpf5ExpectTranslationExists($errorCode);
        } else {
            expect(fn () => app(CaptureCashPayment::class)($command), $case)->toThrow($exceptionClass);
        }

        fcpf5ExpectFinancialCounts($before, $case);
    }

    $first = fcpf5Fixture('case-key-first');
    fcpf5ActingIn($first, requestId: 'fcpf5-case-key-first');
    app(CaptureCashPayment::class)(fcpf5Command($first, 'Case-Sensitive-Key'));

    $second = fcpf5Fixture('case-key-second');
    fcpf5ActingIn($second, requestId: 'fcpf5-case-key-second');
    app(CaptureCashPayment::class)(fcpf5Command($second, 'case-sensitive-key'));

    expect(DB::table('payments')->where('idempotency_key', 'Case-Sensitive-Key')->count())->toBe(1)
        ->and(DB::table('payments')->where('idempotency_key', 'case-sensitive-key')->count())->toBe(1);

    $trimmed = fcpf5Fixture('no-trim-normalization');
    fcpf5ActingIn($trimmed, requestId: 'fcpf5-no-trim-normalization');
    app(CaptureCashPayment::class)(fcpf5Command($trimmed, 'not-trimmed'));

    $beforeWhitespace = fcpf5FinancialCounts();
    fcpf5ExpectPaymentDomainCode(
        fn (): mixed => app(CaptureCashPayment::class)(fcpf5Command($trimmed, ' not-trimmed ')),
        'payments.idempotency_key_whitespace',
    );
    fcpf5ExpectFinancialCounts($beforeWhitespace);
});

it('requires the selected active cashbox in the current tenant and branch', function (): void {
    $record = fcpf5Fixture('cashbox-rules');
    fcpf5ActingIn($record, requestId: 'fcpf5-cashbox-rules');

    $inactive = fcpf5Cashbox($record['branch'], 'Inactive Register', isActive: false, isDefault: false);

    fcpf5ExpectPaymentDomainCode(
        fn (): mixed => app(CaptureCashPayment::class)(fcpf5Command($record, 'inactive-cashbox-key', cashbox: $inactive)),
        'payments.cashbox_unavailable',
        fcpf5FinancialCounts(),
    );

    foreach ([
        'missing cashbox' => 999_999,
        'no implicit default selection' => 0,
    ] as $case => $cashboxId) {
        $before = fcpf5FinancialCounts();

        expect(fn () => app(CaptureCashPayment::class)(new CaptureCashPaymentCommand(
            orderId: $record['order_id'],
            cashboxId: $cashboxId,
            expectedAmountMinor: 6_500,
            expectedCurrency: 'AMD',
            idempotencyKey: "cashbox-{$case}",
        )), $case)->toThrow(ModelNotFoundException::class);

        fcpf5ExpectFinancialCounts($before, $case);
    }

    $otherBranch = fcpf5Branch($record['tenant'], 'Cashbox Rules Other Branch');
    $foreignBranchCashbox = fcpf5Cashbox($otherBranch, 'Other Branch Register');
    $foreignTenant = fcpf5Fixture('cashbox-rules-foreign-tenant');

    foreach ([
        'cross branch cashbox' => (int) $foreignBranchCashbox->id,
        'cross tenant cashbox' => $foreignTenant['cashbox_id'],
    ] as $case => $cashboxId) {
        fcpf5ActingIn($record, requestId: "fcpf5-{$case}");
        $before = fcpf5FinancialCounts();

        expect(fn () => app(CaptureCashPayment::class)(new CaptureCashPaymentCommand(
            orderId: $record['order_id'],
            cashboxId: $cashboxId,
            expectedAmountMinor: 6_500,
            expectedCurrency: 'AMD',
            idempotencyKey: "cashbox-{$case}",
        )), $case)->toThrow(ModelNotFoundException::class);

        fcpf5ExpectFinancialCounts($before, $case);
    }
});

it('keeps payment capture dependent only on the orders public payable contract', function (): void {
    $source = file_get_contents(app_path('Modules/Payments/Application/CaptureCashPayment.php'));

    expect($source)->toContain('App\Modules\Orders\Contracts\PayableOrderReader')
        ->and($source)->toContain('lockPayableForUpdate')
        ->and($source)->not->toContain('App\Modules\Orders\Domain')
        ->and($source)->not->toContain('App\Modules\Orders\Application')
        ->and($source)->not->toContain('App\Modules\Orders\Infrastructure')
        ->and($source)->not->toContain('App\Modules\Orders\Http')
        ->and($source)->not->toContain("DB::table('orders")
        ->and($source)->not->toContain('DB::table("orders');
});

it('preserves payable order not-found and domain behavior without financial writes', function (): void {
    $record = fcpf5Fixture('order-rules');
    $foreignTenant = fcpf5Fixture('order-rules-foreign-tenant');
    fcpf5ActingIn($record, requestId: 'fcpf5-order-rules');

    $otherBranch = fcpf5Branch($record['tenant'], 'Order Rules Other Branch');
    $foreignBranchOrder = fcpf5Order($otherBranch, 'foreign-branch-order', totalMinor: 6_500);
    $closedOrder = fcpf5Order($record['branch'], 'closed-order', status: 'closed', totalMinor: 6_500);
    $zeroOrder = fcpf5Order($record['branch'], 'zero-order', totalMinor: 0);

    foreach ([
        'missing order' => 999_999,
        'cross tenant order' => $foreignTenant['order_id'],
        'cross branch order' => (int) $foreignBranchOrder->id,
    ] as $case => $orderId) {
        fcpf5ActingIn($record, requestId: "fcpf5-{$case}");
        $before = fcpf5FinancialCounts();

        expect(fn () => app(CaptureCashPayment::class)(new CaptureCashPaymentCommand(
            orderId: $orderId,
            cashboxId: $record['cashbox_id'],
            expectedAmountMinor: 6_500,
            expectedCurrency: 'AMD',
            idempotencyKey: "order-{$case}",
        )), $case)->toThrow(ModelNotFoundException::class);

        fcpf5ExpectFinancialCounts($before, $case);
    }

    foreach ([
        'closed order' => [(int) $closedOrder->id, 6_500, 'orders.order_not_open'],
        'zero order' => [(int) $zeroOrder->id, 1, 'orders.order_not_payable'],
    ] as $case => [$orderId, $expectedAmount, $errorCode]) {
        fcpf5ActingIn($record, requestId: "fcpf5-{$case}");
        $before = fcpf5FinancialCounts();

        try {
            app(CaptureCashPayment::class)(new CaptureCashPaymentCommand(
                orderId: $orderId,
                cashboxId: $record['cashbox_id'],
                expectedAmountMinor: $expectedAmount,
                expectedCurrency: 'AMD',
                idempotencyKey: "order-{$case}",
            ));
            $this->fail("Expected {$errorCode}.");
        } catch (OrdersDomainException $exception) {
            expect($exception->errorCode(), $case)->toBe($errorCode);
        }

        fcpf5ExpectFinancialCounts($before, $case);
    }
});

it('does not close or mutate order workflow rows or order items', function (): void {
    $record = fcpf5Fixture('order-unchanged');
    fcpf5ActingIn($record, requestId: 'fcpf5-order-unchanged');
    DB::table('order_items')->insert([
        'tenant_id' => $record['tenant_id'],
        'branch_id' => $record['branch_id'],
        'order_id' => $record['order_id'],
        'subtable_id' => null,
        'menu_item_id' => 12345,
        'qty' => 2,
        'unit_price_minor' => 3_000,
        'discount_minor' => 0,
        'total_minor' => 6_000,
        'currency' => 'AMD',
        'seller_id' => $record['user_id'],
        'preparation_status' => 'pending',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $orderBefore = (array) DB::table('orders')->where('id', $record['order_id'])->first();
    $itemsBefore = DB::table('order_items')->where('order_id', $record['order_id'])->orderBy('id')->get()->map(fn (stdClass $row): array => (array) $row)->all();

    app(CaptureCashPayment::class)(fcpf5Command($record, 'order-unchanged-key'));

    $orderAfter = (array) DB::table('orders')->where('id', $record['order_id'])->first();
    $itemsAfter = DB::table('order_items')->where('order_id', $record['order_id'])->orderBy('id')->get()->map(fn (stdClass $row): array => (array) $row)->all();

    expect($orderAfter)->toBe($orderBefore)
        ->and($itemsAfter)->toBe($itemsBefore);
});

it('calculates remaining balance from captured payment allocations only', function (): void {
    $record = fcpf5Fixture('remaining-balance', totalMinor: 10_000);
    fcpf5ActingIn($record, requestId: 'fcpf5-remaining-balance');

    fcpf5PaymentFact($record, 'captured-prior', amountMinor: 4_000, allocationMinor: 4_000);

    $result = app(CaptureCashPayment::class)(fcpf5Command($record, 'remaining-capture-key', expectedAmountMinor: 6_000));

    expect($result->amountMinor)->toBe(6_000)
        ->and(Payment::query()->whereKey($result->paymentId)->sole()->amount_minor)->toBe(6_000)
        ->and(PaymentAllocation::query()->where('payment_id', $result->paymentId)->sole()->amount_minor)->toBe(6_000);

    $noAllocation = fcpf5Fixture('remaining-no-allocation', totalMinor: 10_000);
    fcpf5ActingIn($noAllocation, requestId: 'fcpf5-remaining-no-allocation');
    fcpf5PaymentFact($noAllocation, 'orphan-payment', amountMinor: 4_000, allocationMinor: null);

    $noAllocationResult = app(CaptureCashPayment::class)(fcpf5Command($noAllocation, 'no-allocation-key', expectedAmountMinor: 10_000));

    expect($noAllocationResult->amountMinor)->toBe(10_000);

    $nonCaptured = fcpf5Fixture('remaining-noncaptured', totalMinor: 10_000);
    fcpf5ActingIn($nonCaptured, requestId: 'fcpf5-remaining-noncaptured');
    fcpf5PaymentFact($nonCaptured, 'authorized-prior', amountMinor: 4_000, allocationMinor: 4_000, status: 'authorized');

    $nonCapturedResult = app(CaptureCashPayment::class)(fcpf5Command($nonCaptured, 'noncaptured-key', expectedAmountMinor: 10_000));

    expect($nonCapturedResult->amountMinor)->toBe(10_000);
});

it('rejects fully paid over allocated and mismatched expected remaining values', function (): void {
    $fullyPaid = fcpf5Fixture('fully-paid', totalMinor: 10_000);
    fcpf5ActingIn($fullyPaid, requestId: 'fcpf5-fully-paid');
    fcpf5PaymentFact($fullyPaid, 'fully-paid-prior', amountMinor: 10_000, allocationMinor: 10_000);

    fcpf5ExpectPaymentDomainCode(
        fn (): mixed => app(CaptureCashPayment::class)(fcpf5Command($fullyPaid, 'fully-paid-key', expectedAmountMinor: 10_000)),
        'payments.order_already_fully_paid',
        fcpf5FinancialCounts(),
    );

    $overAllocated = fcpf5Fixture('over-allocated', totalMinor: 10_000);
    fcpf5ActingIn($overAllocated, requestId: 'fcpf5-over-allocated');
    fcpf5PaymentFact($overAllocated, 'over-allocated-prior', amountMinor: 10_001, allocationMinor: 10_001);

    fcpf5ExpectPaymentDomainCode(
        fn (): mixed => app(CaptureCashPayment::class)(fcpf5Command($overAllocated, 'over-allocated-key', expectedAmountMinor: 10_000)),
        'payments.order_over_allocated',
        fcpf5FinancialCounts(),
    );

    foreach ([
        'under expected amount' => [6_499, 'payments.expected_amount_mismatch'],
        'over expected amount' => [6_501, 'payments.expected_amount_mismatch'],
    ] as $case => [$expectedAmount, $errorCode]) {
        $record = fcpf5Fixture(str_replace(' ', '-', $case));
        fcpf5ActingIn($record, requestId: "fcpf5-{$case}");

        fcpf5ExpectPaymentDomainCode(
            fn (): mixed => app(CaptureCashPayment::class)(fcpf5Command($record, "expected-{$case}", expectedAmountMinor: $expectedAmount)),
            $errorCode,
            fcpf5FinancialCounts(),
        );
    }

    $currency = fcpf5Fixture('currency-mismatch');
    fcpf5ActingIn($currency, requestId: 'fcpf5-currency-mismatch');

    fcpf5ExpectPaymentDomainCode(
        fn (): mixed => app(CaptureCashPayment::class)(fcpf5Command($currency, 'currency-mismatch-key', expectedCurrency: 'USD')),
        'payments.expected_currency_mismatch',
        fcpf5FinancialCounts(),
    );
});

it('handles sequential idempotency replay conflict and scope exactly', function (): void {
    $record = fcpf5Fixture('idempotency');
    fcpf5ActingIn($record, requestId: 'fcpf5-idempotency');

    $first = app(CaptureCashPayment::class)(fcpf5Command($record, 'idempotent-key'));
    $second = app(CaptureCashPayment::class)(fcpf5Command($record, 'idempotent-key'));

    expect($second->paymentId)->toBe($first->paymentId)
        ->and($second->paymentAllocationId)->toBe($first->paymentAllocationId)
        ->and($second->cashboxEntryId)->toBe($first->cashboxEntryId)
        ->and($second->tenantId)->toBe($first->tenantId)
        ->and($second->branchId)->toBe($first->branchId)
        ->and($second->orderId)->toBe($first->orderId)
        ->and($second->cashboxId)->toBe($first->cashboxId)
        ->and($second->amountMinor)->toBe($first->amountMinor)
        ->and($second->currency)->toBe($first->currency)
        ->and($second->idempotencyKey)->toBe($first->idempotencyKey)
        ->and($second->idempotencyFingerprint)->toBe($first->idempotencyFingerprint)
        ->and($second->replayed)->toBeTrue()
        ->and(DB::table('payments')->count())->toBe(1)
        ->and(DB::table('payment_allocations')->count())->toBe(1)
        ->and(DB::table('cashbox_entries')->count())->toBe(1)
        ->and(DB::table('audit_logs')->where('action', 'payments.payment.captured')->count())->toBe(1);

    $secondOrder = fcpf5Order($record['branch'], 'idempotency-second-order', totalMinor: 6_500);
    $secondCashbox = fcpf5Cashbox($record['branch'], 'Second Register', isDefault: false);

    foreach ([
        'different order' => new CaptureCashPaymentCommand((int) $secondOrder->id, $record['cashbox_id'], 6_500, 'AMD', 'idempotent-key'),
        'different cashbox' => new CaptureCashPaymentCommand($record['order_id'], (int) $secondCashbox->id, 6_500, 'AMD', 'idempotent-key'),
        'different amount' => new CaptureCashPaymentCommand($record['order_id'], $record['cashbox_id'], 6_499, 'AMD', 'idempotent-key'),
        'different currency' => new CaptureCashPaymentCommand($record['order_id'], $record['cashbox_id'], 6_500, 'USD', 'idempotent-key'),
    ] as $case => $command) {
        fcpf5ExpectPaymentDomainCode(
            fn (): mixed => app(CaptureCashPayment::class)($command),
            'payments.idempotency_conflict',
            fcpf5FinancialCounts(),
            $case,
        );
    }

    $otherTenant = fcpf5Fixture('idempotency-other-tenant');
    fcpf5ActingIn($otherTenant, requestId: 'fcpf5-idempotency-other-tenant');
    $otherTenantResult = app(CaptureCashPayment::class)(fcpf5Command($otherTenant, 'idempotent-key'));

    $otherBranch = fcpf5Branch($record['tenant'], 'Idempotency Other Branch');
    $otherBranchOrder = fcpf5Order($otherBranch, 'idempotency-other-branch', totalMinor: 6_500);
    $otherBranchCashbox = fcpf5Cashbox($otherBranch, 'Other Branch Register');
    fcpf5ActingIn($record, branch: $otherBranch, requestId: 'fcpf5-idempotency-other-branch');
    $otherBranchResult = app(CaptureCashPayment::class)(new CaptureCashPaymentCommand(
        orderId: (int) $otherBranchOrder->id,
        cashboxId: (int) $otherBranchCashbox->id,
        expectedAmountMinor: 6_500,
        expectedCurrency: 'AMD',
        idempotencyKey: 'idempotent-key',
    ));

    expect($otherTenantResult->paymentId)->not->toBe($first->paymentId)
        ->and($otherBranchResult->paymentId)->not->toBe($first->paymentId)
        ->and(DB::table('payments')->where('idempotency_key', 'idempotent-key')->count())->toBe(3);
});

it('rolls back all facts on audit financial domain and authorization failures', function (): void {
    $auditFailure = fcpf5Fixture('audit-failure');
    fcpf5ActingIn($auditFailure, requestId: 'fcpf5-audit-failure');
    app()->instance(AuditRecorder::class, new class implements AuditRecorder
    {
        public function record(string $action, string $targetType, int $targetId, ?array $before = null, ?array $after = null): AuditLog
        {
            throw new RuntimeException('FCPF5 audit failure.');
        }
    });

    expect(fn () => app(CaptureCashPayment::class)(fcpf5Command($auditFailure, 'audit-failure-key')))
        ->toThrow(RuntimeException::class, 'FCPF5 audit failure.');
    fcpf5ExpectFinancialCounts(['payments' => 0, 'payment_allocations' => 0, 'cashbox_entries' => 0, 'payment_audits' => 0]);

    $domainFailure = fcpf5Fixture('domain-failure');
    fcpf5ActingIn($domainFailure, requestId: 'fcpf5-domain-failure');
    fcpf5ExpectPaymentDomainCode(
        fn (): mixed => app(CaptureCashPayment::class)(fcpf5Command($domainFailure, 'domain-failure-key', expectedAmountMinor: 1)),
        'payments.expected_amount_mismatch',
        fcpf5FinancialCounts(),
    );

    $authorizationFailure = fcpf5Fixture('authorization-failure', grantCapture: false);
    fcpf5ActingIn($authorizationFailure, requestId: 'fcpf5-authorization-failure');
    $beforeAuthorization = fcpf5FinancialCounts();

    expect(fn () => app(CaptureCashPayment::class)(fcpf5Command($authorizationFailure, 'authorization-failure-key')))
        ->toThrow(AuthorizationException::class);
    fcpf5ExpectFinancialCounts($beforeAuthorization);

    $financialFailure = fcpf5Fixture('financial-failure');
    fcpf5ActingIn($financialFailure, requestId: 'fcpf5-financial-failure');
    app()->instance(Authorizer::class, new class implements Authorizer
    {
        public function allows(Authenticatable $user, string $permission): bool
        {
            return $permission === PaymentPermissions::CAPTURE;
        }
    });
    $fakeActor = new class($financialFailure['tenant_id']) implements Authenticatable
    {
        public int $tenant_id;

        public function __construct(int $tenantId)
        {
            $this->tenant_id = $tenantId;
        }

        public function getAuthIdentifierName(): string
        {
            return 'id';
        }

        public function getAuthIdentifier(): int
        {
            return 999_999;
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
    };
    Auth::guard()->setUser($fakeActor);
    $beforeFinancial = fcpf5FinancialCounts();

    expect(fn () => app(CaptureCashPayment::class)(fcpf5Command($financialFailure, 'financial-failure-key')))
        ->toThrow(QueryException::class);
    fcpf5ExpectFinancialCounts($beforeFinancial);
});

it('logs stable safe success and replay context', function (): void {
    $record = fcpf5Fixture('logging');
    fcpf5ActingIn($record, requestId: 'fcpf5-logging');
    Log::spy();

    app(CaptureCashPayment::class)(fcpf5Command($record, 'logging-key'));
    app(CaptureCashPayment::class)(fcpf5Command($record, 'logging-key'));

    foreach ([false, true] as $replayed) {
        Log::shouldHaveReceived('info')
            ->with('action performed', Mockery::on(fn (array $context): bool => ($context['action'] ?? null) === 'payments.capture_cash_payment'
                && ($context['tenant_id'] ?? null) === $record['tenant_id']
                && ($context['branch_id'] ?? null) === $record['branch_id']
                && ($context['order_id'] ?? null) === $record['order_id']
                && ($context['cashbox_id'] ?? null) === $record['cashbox_id']
                && ($context['amount_minor'] ?? null) === 6_500
                && ($context['currency'] ?? null) === 'AMD'
                && ($context['replayed'] ?? null) === $replayed
                && ! array_key_exists('password', $context)
                && ! array_key_exists('email', $context)
                && ! array_key_exists('name', $context)
                && ! array_key_exists('idempotency_key', $context)))
            ->once();
    }
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
function fcpf5Fixture(
    string $suffix,
    bool $grantCapture = true,
    bool $activeUser = true,
    bool $superadmin = false,
    int $totalMinor = 6_500,
    string $currency = 'AMD',
    bool $cashboxActive = true,
): array {
    $tenant = Tenant::query()->create([
        'name' => "FCPF5 {$suffix} Tenant",
        'slug' => "fcpf5-{$suffix}",
        'default_locale' => 'en',
        'currency' => $currency,
        'status' => 'active',
    ]);

    app(TenantResolver::class)->set((int) $tenant->id);

    $branch = fcpf5Branch($tenant, "FCPF5 {$suffix} Branch");

    app(BranchContext::class)->set((int) $branch->id);

    $role = Role::query()->create([
        'code' => "fcpf5-{$suffix}-role",
        'name' => "FCPF5 {$suffix} Role",
        'is_management_role' => false,
    ]);

    if ($grantCapture) {
        $permission = Permission::query()->firstOrCreate(
            ['code' => PaymentPermissions::CAPTURE],
            ['name' => 'Capture payments'],
        );
        $role->permissions()->attach((int) $permission->id, ['tenant_id' => (int) $tenant->id]);
    }

    $user = User::query()->create([
        'role_id' => (int) $role->id,
        'name' => "FCPF5 {$suffix} Cashier",
        'email' => "fcpf5-{$suffix}@smartrest.test",
        'username' => "fcpf5-{$suffix}",
        'default_locale' => 'en',
        'active' => $activeUser,
        'is_superadmin' => $superadmin,
        'password' => Hash::make('password'),
    ]);

    UserBranchAssignment::query()->create([
        'user_id' => (int) $user->id,
        'branch_id' => (int) $branch->id,
    ]);

    $order = fcpf5Order($branch, "{$suffix}-order", totalMinor: $totalMinor, currency: $currency);
    $cashbox = fcpf5Cashbox($branch, "FCPF5 {$suffix} Register", isActive: $cashboxActive);

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

function fcpf5Branch(Tenant $tenant, string $name): Branch
{
    app(TenantResolver::class)->set((int) $tenant->id);

    return Branch::query()->create([
        'name' => $name,
        'timezone' => 'Asia/Yerevan',
        'status' => 'active',
    ]);
}

function fcpf5Order(
    Branch $branch,
    string $suffix,
    string $status = 'open',
    int $totalMinor = 6_500,
    string $currency = 'AMD',
): Order {
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
        'currency' => $currency,
        'comment' => "FCPF5 {$suffix}",
    ]);
}

function fcpf5Cashbox(Branch $branch, string $name, bool $isActive = true, bool $isDefault = true): Cashbox
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
 * @param  array{tenant_id: int, branch_id: int, user: User, branch: Branch}  $record
 */
function fcpf5ActingIn(array $record, ?User $user = null, ?Branch $branch = null, string $requestId = 'fcpf5-request'): void
{
    app(TenantResolver::class)->set($record['tenant_id']);
    app(BranchContext::class)->set((int) ($branch?->id ?? $record['branch_id']));
    auth()->login($user ?? $record['user']);
    LogContext::start($requestId, 'payments');
}

/**
 * @param  array{order: Order, cashbox: Cashbox}  $record
 */
function fcpf5Command(
    array $record,
    string $idempotencyKey,
    ?Order $order = null,
    ?Cashbox $cashbox = null,
    ?int $expectedAmountMinor = null,
    ?string $expectedCurrency = null,
): CaptureCashPaymentCommand {
    return new CaptureCashPaymentCommand(
        orderId: (int) ($order?->id ?? $record['order']->id),
        cashboxId: (int) ($cashbox?->id ?? $record['cashbox']->id),
        expectedAmountMinor: $expectedAmountMinor ?? (int) ($order?->total_minor ?? $record['order']->total_minor),
        expectedCurrency: $expectedCurrency ?? (string) ($order?->currency ?? $record['order']->currency),
        idempotencyKey: $idempotencyKey,
    );
}

/**
 * @param  array{tenant_id: int, branch_id: int, order_id: int, cashbox_id: int, user_id: int}  $record
 */
function fcpf5PaymentFact(
    array $record,
    string $idempotencyKey,
    int $amountMinor,
    ?int $allocationMinor,
    string $status = 'captured',
): Payment {
    app(TenantResolver::class)->set($record['tenant_id']);
    app(BranchContext::class)->set($record['branch_id']);

    $payment = Payment::query()->create([
        'branch_id' => $record['branch_id'],
        'order_id' => $record['order_id'],
        'cashbox_id' => $record['cashbox_id'],
        'method' => 'cash',
        'status' => $status,
        'amount_minor' => $amountMinor,
        'currency' => 'AMD',
        'idempotency_key' => $idempotencyKey,
        'idempotency_fingerprint' => hash('sha256', $idempotencyKey),
    ]);

    if ($allocationMinor !== null) {
        PaymentAllocation::query()->create([
            'branch_id' => $record['branch_id'],
            'payment_id' => (int) $payment->id,
            'payable_type' => 'order',
            'payable_id' => $record['order_id'],
            'amount_minor' => $allocationMinor,
            'currency' => 'AMD',
        ]);
    }

    return $payment;
}

/**
 * @return array{payments: int, payment_allocations: int, cashbox_entries: int, payment_audits: int}
 */
function fcpf5FinancialCounts(): array
{
    return [
        'payments' => DB::table('payments')->count(),
        'payment_allocations' => DB::table('payment_allocations')->count(),
        'cashbox_entries' => DB::table('cashbox_entries')->count(),
        'payment_audits' => DB::table('audit_logs')->where('action', 'payments.payment.captured')->count(),
    ];
}

/**
 * @param  array{payments: int, payment_allocations: int, cashbox_entries: int, payment_audits: int}  $expected
 */
function fcpf5ExpectFinancialCounts(array $expected, string $case = ''): void
{
    expect(fcpf5FinancialCounts(), $case)->toBe($expected);
}

/**
 * @param  array{payments: int, payment_allocations: int, cashbox_entries: int, payment_audits: int}|null  $expectedCounts
 */
function fcpf5ExpectPaymentDomainCode(Closure $callback, string $errorCode, ?array $expectedCounts = null, string $case = ''): void
{
    try {
        $callback();
        throw new RuntimeException("Expected {$errorCode}.");
    } catch (PaymentsDomainException $exception) {
        expect($exception->errorCode(), $case)->toBe($errorCode);
    }

    if ($expectedCounts !== null) {
        fcpf5ExpectFinancialCounts($expectedCounts, $case);
    }
}

function fcpf5ExpectTranslationExists(string $errorCode): void
{
    foreach (['en', 'hy', 'ru'] as $locale) {
        app()->setLocale($locale);
        expect(__($errorCode), "{$locale}:{$errorCode}")->not->toBe($errorCode);
    }
}
