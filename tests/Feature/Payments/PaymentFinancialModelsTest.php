<?php

declare(strict_types=1);

use App\Modules\Payments\Infrastructure\Models\Cashbox;
use App\Modules\Payments\Infrastructure\Models\CashboxEntry;
use App\Modules\Payments\Infrastructure\Models\Payment;
use App\Modules\Payments\Infrastructure\Models\PaymentAllocation;
use App\Modules\Tenancy\Contracts\BelongsToTenant;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Modules\Tenancy\Infrastructure\Models\Branch;
use App\Modules\Tenancy\Infrastructure\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

afterEach(function (): void {
    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();
});

it('maps payment financial models with tenant scoping casts and relationships', function (): void {
    $tenantA = paymentFinancialModelsRecords('tenant-a', 'alice', 2_500);
    $tenantB = paymentFinancialModelsRecords('tenant-b', 'bob', 3_100);

    app(TenantResolver::class)->clear();

    expect(Payment::query()->count())->toBe(0)
        ->and(PaymentAllocation::query()->count())->toBe(0)
        ->and(CashboxEntry::query()->count())->toBe(0);

    app(TenantResolver::class)->set($tenantA['tenant_id']);

    $payment = Payment::query()
        ->with(['allocations', 'cashbox', 'cashboxEntries'])
        ->sole();
    $allocation = PaymentAllocation::query()->sole();
    $cashboxEntry = CashboxEntry::query()->with(['cashbox', 'sourcePayment'])->sole();

    expect($payment->id)->toBe($tenantA['payment']->id)
        ->and(Payment::query()->whereKey($tenantB['payment']->id)->first())->toBeNull()
        ->and(PaymentAllocation::query()->whereKey($tenantB['allocation']->id)->first())->toBeNull()
        ->and(CashboxEntry::query()->whereKey($tenantB['cashbox_entry']->id)->first())->toBeNull()
        ->and($payment->tenant_id)->toBe($tenantA['tenant_id'])
        ->and($payment->branch_id)->toBe($tenantA['branch_id'])
        ->and($payment->order_id)->toBe($tenantA['order_id'])
        ->and($payment->cashbox_id)->toBe($tenantA['cashbox_id'])
        ->and($payment->amount_minor)->toBe(2_500)
        ->and(is_int($payment->amount_minor))->toBeTrue()
        ->and($payment->currency)->toBe('AMD')
        ->and($payment->cashbox->is($tenantA['cashbox']))->toBeTrue()
        ->and($payment->allocations)->toHaveCount(1)
        ->and($payment->allocations->first()?->is($allocation))->toBeTrue()
        ->and($payment->cashboxEntries)->toHaveCount(1)
        ->and($payment->cashboxEntries->first()?->is($cashboxEntry))->toBeTrue()
        ->and($allocation->tenant_id)->toBe($tenantA['tenant_id'])
        ->and($allocation->branch_id)->toBe($tenantA['branch_id'])
        ->and($allocation->payment_id)->toBe($payment->id)
        ->and($allocation->payable_type)->toBe('order')
        ->and($allocation->payable_id)->toBe($tenantA['order_id'])
        ->and($allocation->amount_minor)->toBe(2_500)
        ->and(is_int($allocation->amount_minor))->toBeTrue()
        ->and($allocation->currency)->toBe('AMD')
        ->and($allocation->payment->is($payment))->toBeTrue()
        ->and($cashboxEntry->tenant_id)->toBe($tenantA['tenant_id'])
        ->and($cashboxEntry->branch_id)->toBe($tenantA['branch_id'])
        ->and($cashboxEntry->cashbox_id)->toBe($tenantA['cashbox_id'])
        ->and($cashboxEntry->direction)->toBe('in')
        ->and($cashboxEntry->amount_minor)->toBe(2_500)
        ->and(is_int($cashboxEntry->amount_minor))->toBeTrue()
        ->and($cashboxEntry->currency)->toBe('AMD')
        ->and($cashboxEntry->reason)->toBe('cash_payment')
        ->and($cashboxEntry->source_type)->toBe('payment')
        ->and($cashboxEntry->source_id)->toBe($payment->id)
        ->and($cashboxEntry->posted_by_id)->toBe($tenantA['posted_by_id'])
        ->and($cashboxEntry->cashbox->is($tenantA['cashbox']))->toBeTrue()
        ->and($cashboxEntry->sourcePayment->is($payment))->toBeTrue();
});

it('prevents soft deletes and persisted model mutations for financial rows', function (): void {
    $records = paymentFinancialModelsRecords('append-only', 'cashier', 4_200);

    expect(class_uses_recursive(Payment::class))->toContain(BelongsToTenant::class)
        ->and(class_uses_recursive(PaymentAllocation::class))->toContain(BelongsToTenant::class)
        ->and(class_uses_recursive(CashboxEntry::class))->toContain(BelongsToTenant::class)
        ->and(class_uses_recursive(Payment::class))->not->toContain(SoftDeletes::class)
        ->and(class_uses_recursive(PaymentAllocation::class))->not->toContain(SoftDeletes::class)
        ->and(class_uses_recursive(CashboxEntry::class))->not->toContain(SoftDeletes::class);

    paymentFinancialModelsExpectModelUpdateRejected(
        Payment::query()->findOrFail($records['payment']->id),
        'status',
        'compromised',
        'Payments are append-only and cannot be updated.',
    );
    paymentFinancialModelsExpectModelUpdateRejected(
        PaymentAllocation::query()->findOrFail($records['allocation']->id),
        'amount_minor',
        1,
        'Payment allocations are append-only and cannot be updated.',
    );
    paymentFinancialModelsExpectModelUpdateRejected(
        CashboxEntry::query()->findOrFail($records['cashbox_entry']->id),
        'amount_minor',
        1,
        'Cashbox entries are append-only and cannot be updated.',
    );

    expect(fn () => Payment::query()->findOrFail($records['payment']->id)->update(['status' => 'compromised']))
        ->toThrow(LogicException::class, 'Payments are append-only and cannot be updated.')
        ->and(fn () => PaymentAllocation::query()->findOrFail($records['allocation']->id)->update(['amount_minor' => 1]))
        ->toThrow(LogicException::class, 'Payment allocations are append-only and cannot be updated.')
        ->and(fn () => CashboxEntry::query()->findOrFail($records['cashbox_entry']->id)->update(['amount_minor' => 1]))
        ->toThrow(LogicException::class, 'Cashbox entries are append-only and cannot be updated.')
        ->and(fn () => Payment::query()->findOrFail($records['payment']->id)->delete())
        ->toThrow(LogicException::class, 'Payments are append-only and cannot be deleted.')
        ->and(fn () => PaymentAllocation::query()->findOrFail($records['allocation']->id)->delete())
        ->toThrow(LogicException::class, 'Payment allocations are append-only and cannot be deleted.')
        ->and(fn () => CashboxEntry::query()->findOrFail($records['cashbox_entry']->id)->delete())
        ->toThrow(LogicException::class, 'Cashbox entries are append-only and cannot be deleted.')
        ->and(fn () => Payment::query()->findOrFail($records['payment']->id)->forceDelete())
        ->toThrow(LogicException::class, 'Payments are append-only and cannot be deleted.')
        ->and(fn () => PaymentAllocation::query()->findOrFail($records['allocation']->id)->forceDelete())
        ->toThrow(LogicException::class, 'Payment allocations are append-only and cannot be deleted.')
        ->and(fn () => CashboxEntry::query()->findOrFail($records['cashbox_entry']->id)->forceDelete())
        ->toThrow(LogicException::class, 'Cashbox entries are append-only and cannot be deleted.');
});

it('keeps database append-only protections for builder mutation helpers', function (): void {
    $records = paymentFinancialModelsRecords('builder-append-only', 'builder-cashier', 5_300);

    expect(fn () => Payment::query()->whereKey($records['payment']->id)->increment('amount_minor'))
        ->toThrow(QueryException::class)
        ->and(fn () => Payment::query()->whereKey($records['payment']->id)->decrement('amount_minor'))
        ->toThrow(QueryException::class)
        ->and(fn () => Payment::query()->whereKey($records['payment']->id)->update(['status' => 'compromised']))
        ->toThrow(QueryException::class)
        ->and(fn () => Payment::query()->whereKey($records['payment']->id)->delete())
        ->toThrow(QueryException::class)
        ->and(fn () => PaymentAllocation::query()->whereKey($records['allocation']->id)->increment('amount_minor'))
        ->toThrow(QueryException::class)
        ->and(fn () => PaymentAllocation::query()->whereKey($records['allocation']->id)->decrement('amount_minor'))
        ->toThrow(QueryException::class)
        ->and(fn () => PaymentAllocation::query()->whereKey($records['allocation']->id)->update(['amount_minor' => 1]))
        ->toThrow(QueryException::class)
        ->and(fn () => PaymentAllocation::query()->whereKey($records['allocation']->id)->delete())
        ->toThrow(QueryException::class)
        ->and(fn () => CashboxEntry::query()->whereKey($records['cashbox_entry']->id)->increment('amount_minor'))
        ->toThrow(QueryException::class)
        ->and(fn () => CashboxEntry::query()->whereKey($records['cashbox_entry']->id)->decrement('amount_minor'))
        ->toThrow(QueryException::class)
        ->and(fn () => CashboxEntry::query()->whereKey($records['cashbox_entry']->id)->update(['amount_minor' => 1]))
        ->toThrow(QueryException::class)
        ->and(fn () => CashboxEntry::query()->whereKey($records['cashbox_entry']->id)->delete())
        ->toThrow(QueryException::class);
});

/**
 * @return array{
 *     tenant_id: int,
 *     branch_id: int,
 *     posted_by_id: int,
 *     order_id: int,
 *     cashbox_id: int,
 *     cashbox: Cashbox,
 *     payment: Payment,
 *     allocation: PaymentAllocation,
 *     cashbox_entry: CashboxEntry
 * }
 */
function paymentFinancialModelsRecords(string $tenantSlug, string $cashierUsername, int $amountMinor): array
{
    $tenant = Tenant::query()->create([
        'name' => str($tenantSlug)->headline()->toString(),
        'slug' => $tenantSlug,
        'default_locale' => 'en',
        'currency' => 'AMD',
        'status' => 'active',
    ]);

    app(TenantResolver::class)->set((int) $tenant->id);

    $branch = Branch::query()->create([
        'name' => "{$tenantSlug} Branch",
        'timezone' => 'Asia/Yerevan',
        'status' => 'active',
    ]);
    app(BranchContext::class)->set((int) $branch->id);

    $postedById = (int) DB::table('users')->insertGetId([
        'tenant_id' => (int) $tenant->id,
        'role_id' => null,
        'name' => str($cashierUsername)->headline()->toString(),
        'email' => "{$cashierUsername}@smartrest.test",
        'username' => $cashierUsername,
        'default_locale' => 'en',
        'active' => true,
        'is_superadmin' => false,
        'password' => 'password',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $orderId = (int) DB::table('orders')->insertGetId([
        'tenant_id' => (int) $tenant->id,
        'branch_id' => (int) $branch->id,
        'type' => 'fast_food',
        'status' => 'open',
        'opened_at' => now(),
        'closed_at' => null,
        'subtotal_minor' => $amountMinor,
        'discount_minor' => 0,
        'total_minor' => $amountMinor,
        'currency' => 'AMD',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $cashbox = Cashbox::query()->create([
        'branch_id' => (int) $branch->id,
        'name' => "{$tenantSlug} Cashbox",
        'is_active' => true,
        'is_default' => true,
    ]);

    $payment = Payment::query()->create([
        'branch_id' => (int) $branch->id,
        'order_id' => $orderId,
        'cashbox_id' => (int) $cashbox->id,
        'method' => 'cash',
        'status' => 'captured',
        'amount_minor' => $amountMinor,
        'currency' => 'AMD',
        'idempotency_key' => "capture-{$tenantSlug}",
        'idempotency_fingerprint' => hash('sha256', "capture-{$tenantSlug}"),
    ]);

    $allocation = PaymentAllocation::query()->create([
        'branch_id' => (int) $branch->id,
        'payment_id' => (int) $payment->id,
        'payable_type' => 'order',
        'payable_id' => $orderId,
        'amount_minor' => $amountMinor,
        'currency' => 'AMD',
    ]);

    $cashboxEntry = CashboxEntry::query()->create([
        'branch_id' => (int) $branch->id,
        'cashbox_id' => (int) $cashbox->id,
        'direction' => 'in',
        'amount_minor' => $amountMinor,
        'currency' => 'AMD',
        'reason' => 'cash_payment',
        'source_type' => 'payment',
        'source_id' => (int) $payment->id,
        'posted_by_id' => $postedById,
    ]);

    return [
        'tenant_id' => (int) $tenant->id,
        'branch_id' => (int) $branch->id,
        'posted_by_id' => $postedById,
        'order_id' => $orderId,
        'cashbox_id' => (int) $cashbox->id,
        'cashbox' => $cashbox,
        'payment' => $payment,
        'allocation' => $allocation,
        'cashbox_entry' => $cashboxEntry,
    ];
}

function paymentFinancialModelsExpectModelUpdateRejected(
    Model $model,
    string $attribute,
    mixed $value,
    string $message,
): void {
    $model->setAttribute($attribute, $value);

    expect(fn () => $model->save())->toThrow(LogicException::class, $message);
}
