<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('creates payment financial tables with required columns and query indexes', function (): void {
    expect(Schema::hasTable('payments'))->toBeTrue()
        ->and(Schema::hasColumns('payments', [
            'id',
            'tenant_id',
            'branch_id',
            'order_id',
            'cashbox_id',
            'method',
            'status',
            'amount_minor',
            'currency',
            'idempotency_key',
            'idempotency_fingerprint',
            'created_at',
            'updated_at',
        ]))->toBeTrue()
        ->and(Schema::hasTable('payment_allocations'))->toBeTrue()
        ->and(Schema::hasColumns('payment_allocations', [
            'id',
            'tenant_id',
            'branch_id',
            'payment_id',
            'payable_type',
            'payable_id',
            'amount_minor',
            'currency',
            'created_at',
            'updated_at',
        ]))->toBeTrue()
        ->and(Schema::hasTable('cashbox_entries'))->toBeTrue()
        ->and(Schema::hasColumns('cashbox_entries', [
            'id',
            'tenant_id',
            'branch_id',
            'cashbox_id',
            'direction',
            'amount_minor',
            'currency',
            'reason',
            'source_type',
            'source_id',
            'posted_by_id',
            'created_at',
            'updated_at',
        ]))->toBeTrue()
        ->and(Schema::hasColumn('payments', 'deleted_at'))->toBeFalse()
        ->and(Schema::hasColumn('payment_allocations', 'deleted_at'))->toBeFalse()
        ->and(Schema::hasColumn('cashbox_entries', 'deleted_at'))->toBeFalse();

    $paymentIndexes = paymentFinancialSchemaIndexNames('payments');
    $allocationIndexes = paymentFinancialSchemaIndexNames('payment_allocations');
    $entryIndexes = paymentFinancialSchemaIndexNames('cashbox_entries');

    expect($paymentIndexes)->toContain('payments_tenant_id_index')
        ->and($paymentIndexes)->toContain('payments_branch_id_index')
        ->and($paymentIndexes)->toContain('payments_order_id_index')
        ->and($paymentIndexes)->toContain('payments_cashbox_id_index')
        ->and($paymentIndexes)->toContain('payments_tenant_branch_idempotency_key_unique')
        ->and($paymentIndexes)->toContain('payments_tenant_branch_order_status_id_idx')
        ->and($paymentIndexes)->toContain('payments_tenant_branch_cashbox_status_id_idx')
        ->and($allocationIndexes)->toContain('payment_allocations_tenant_id_index')
        ->and($allocationIndexes)->toContain('payment_allocations_branch_id_index')
        ->and($allocationIndexes)->toContain('payment_allocations_payment_id_index')
        ->and($allocationIndexes)->toContain('payment_allocations_payment_payable_unique')
        ->and($allocationIndexes)->toContain('payment_allocations_tenant_branch_payment_idx')
        ->and($allocationIndexes)->toContain('payment_allocations_tenant_branch_payable_idx')
        ->and($entryIndexes)->toContain('cashbox_entries_tenant_id_index')
        ->and($entryIndexes)->toContain('cashbox_entries_branch_id_index')
        ->and($entryIndexes)->toContain('cashbox_entries_cashbox_id_index')
        ->and($entryIndexes)->toContain('cashbox_entries_posted_by_id_index')
        ->and($entryIndexes)->toContain('cashbox_entries_tenant_branch_source_unique')
        ->and($entryIndexes)->toContain('cashbox_entries_tenant_branch_cashbox_created_id_idx')
        ->and($entryIndexes)->toContain('cashbox_entries_tenant_branch_posted_by_created_idx');
});

it('enforces append-only financial rows and restrictive references at the database layer', function (): void {
    $context = paymentFinancialSchemaContext();
    $paymentId = paymentFinancialSchemaPayment($context);
    $allocationId = paymentFinancialSchemaAllocation($context, $paymentId);
    $entryId = paymentFinancialSchemaCashboxEntry($context, $paymentId);

    expect(fn () => DB::table('payments')->where('id', $paymentId)->update(['status' => 'voided']))
        ->toThrow(QueryException::class)
        ->and(fn () => DB::table('payments')->where('id', $paymentId)->delete())
        ->toThrow(QueryException::class)
        ->and(fn () => DB::table('payment_allocations')->where('id', $allocationId)->update(['amount_minor' => 1]))
        ->toThrow(QueryException::class)
        ->and(fn () => DB::table('payment_allocations')->where('id', $allocationId)->delete())
        ->toThrow(QueryException::class)
        ->and(fn () => DB::table('cashbox_entries')->where('id', $entryId)->update(['amount_minor' => 1]))
        ->toThrow(QueryException::class)
        ->and(fn () => DB::table('cashbox_entries')->where('id', $entryId)->delete())
        ->toThrow(QueryException::class)
        ->and(fn () => DB::table('orders')->where('id', $context['order_id'])->delete())
        ->toThrow(QueryException::class)
        ->and(fn () => DB::table('cashboxes')->where('id', $context['cashbox_id'])->delete())
        ->toThrow(QueryException::class)
        ->and(fn () => DB::table('users')->where('id', $context['posted_by_id'])->delete())
        ->toThrow(QueryException::class);
});

it('rolls the payment financial migration down and reapplies it cleanly', function (): void {
    $migration = require database_path('migrations/2026_08_04_000000_create_payment_capture_financial_tables.php');

    $migration->down();

    expect(Schema::hasTable('cashbox_entries'))->toBeFalse()
        ->and(Schema::hasTable('payment_allocations'))->toBeFalse()
        ->and(Schema::hasTable('payments'))->toBeFalse();

    $migration->up();

    expect(Schema::hasTable('payments'))->toBeTrue()
        ->and(Schema::hasTable('payment_allocations'))->toBeTrue()
        ->and(Schema::hasTable('cashbox_entries'))->toBeTrue();
});

/**
 * @return list<string>
 */
function paymentFinancialSchemaIndexNames(string $table): array
{
    return collect(Schema::getIndexes($table))
        ->pluck('name')
        ->all();
}

/**
 * @return array{tenant_id: int, branch_id: int, order_id: int, cashbox_id: int, posted_by_id: int, amount_minor: int, currency: string}
 */
function paymentFinancialSchemaContext(): array
{
    $tenantId = DB::table('tenants')->insertGetId([
        'name' => 'Payment Financial Tenant',
        'slug' => 'payment-financial-tenant',
        'default_locale' => 'en',
        'currency' => 'AMD',
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $branchId = DB::table('branches')->insertGetId([
        'tenant_id' => $tenantId,
        'name' => 'Payment Financial Branch',
        'timezone' => 'Asia/Yerevan',
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $postedById = DB::table('users')->insertGetId([
        'tenant_id' => $tenantId,
        'role_id' => null,
        'name' => 'Payment Cashier',
        'email' => 'payment-cashier@smartrest.test',
        'username' => 'payment-cashier',
        'default_locale' => 'en',
        'active' => true,
        'password' => 'password',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $orderId = DB::table('orders')->insertGetId([
        'tenant_id' => $tenantId,
        'branch_id' => $branchId,
        'type' => 'fast_food',
        'status' => 'open',
        'opened_at' => now(),
        'client_count' => 1,
        'subtotal_minor' => 2500,
        'discount_minor' => 0,
        'total_minor' => 2500,
        'currency' => 'AMD',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $cashboxId = DB::table('cashboxes')->insertGetId([
        'tenant_id' => $tenantId,
        'branch_id' => $branchId,
        'name' => 'Main Register',
        'is_active' => true,
        'is_default' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return [
        'tenant_id' => (int) $tenantId,
        'branch_id' => (int) $branchId,
        'order_id' => (int) $orderId,
        'cashbox_id' => (int) $cashboxId,
        'posted_by_id' => (int) $postedById,
        'amount_minor' => 2500,
        'currency' => 'AMD',
    ];
}

/**
 * @param  array{tenant_id: int, branch_id: int, order_id: int, cashbox_id: int, amount_minor: int, currency: string}  $context
 */
function paymentFinancialSchemaPayment(array $context, string $key = 'schema-key'): int
{
    return (int) DB::table('payments')->insertGetId([
        'tenant_id' => $context['tenant_id'],
        'branch_id' => $context['branch_id'],
        'order_id' => $context['order_id'],
        'cashbox_id' => $context['cashbox_id'],
        'method' => 'cash',
        'status' => 'captured',
        'amount_minor' => $context['amount_minor'],
        'currency' => $context['currency'],
        'idempotency_key' => $key,
        'idempotency_fingerprint' => hash('sha256', $key),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/**
 * @param  array{tenant_id: int, branch_id: int, order_id: int, amount_minor: int, currency: string}  $context
 */
function paymentFinancialSchemaAllocation(array $context, int $paymentId): int
{
    return (int) DB::table('payment_allocations')->insertGetId([
        'tenant_id' => $context['tenant_id'],
        'branch_id' => $context['branch_id'],
        'payment_id' => $paymentId,
        'payable_type' => 'order',
        'payable_id' => $context['order_id'],
        'amount_minor' => $context['amount_minor'],
        'currency' => $context['currency'],
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/**
 * @param  array{tenant_id: int, branch_id: int, cashbox_id: int, posted_by_id: int, amount_minor: int, currency: string}  $context
 */
function paymentFinancialSchemaCashboxEntry(array $context, int $paymentId): int
{
    return (int) DB::table('cashbox_entries')->insertGetId([
        'tenant_id' => $context['tenant_id'],
        'branch_id' => $context['branch_id'],
        'cashbox_id' => $context['cashbox_id'],
        'direction' => 'in',
        'amount_minor' => $context['amount_minor'],
        'currency' => $context['currency'],
        'reason' => 'payment_capture',
        'source_type' => 'payment',
        'source_id' => $paymentId,
        'posted_by_id' => $context['posted_by_id'],
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}
