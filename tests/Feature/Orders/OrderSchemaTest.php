<?php

declare(strict_types=1);

use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Orders\Infrastructure\Models\OrderItem;
use App\Modules\Orders\Infrastructure\Models\OrderItemMove;
use App\Modules\Orders\Infrastructure\Models\OrderSubtable;
use App\Modules\Tenancy\Contracts\BelongsToTenant;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('creates orders and order subtables schema with tenant branch and lifecycle indexes', function (): void {
    expect(Schema::hasTable('orders'))->toBeTrue()
        ->and(Schema::hasColumns('orders', [
            'id',
            'tenant_id',
            'branch_id',
            'type',
            'status',
            'table_id',
            'customer_id',
            'waiter_id',
            'cashier_id',
            'opened_at',
            'closed_at',
            'client_count',
            'comment',
            'subtotal_minor',
            'discount_minor',
            'total_minor',
            'currency',
            'created_at',
            'updated_at',
        ]))->toBeTrue()
        ->and(Schema::hasTable('order_subtables'))->toBeTrue()
        ->and(Schema::hasColumns('order_subtables', [
            'id',
            'tenant_id',
            'branch_id',
            'order_id',
            'name',
            'status',
            'created_at',
            'updated_at',
        ]))->toBeTrue()
        ->and(Schema::hasTable('order_items'))->toBeTrue()
        ->and(Schema::hasColumns('order_items', [
            'id',
            'tenant_id',
            'branch_id',
            'order_id',
            'subtable_id',
            'menu_item_id',
            'qty',
            'unit_price_minor',
            'discount_minor',
            'total_minor',
            'currency',
            'seller_id',
            'preparation_status',
            'created_at',
            'updated_at',
        ]))->toBeTrue()
        ->and(Schema::hasTable('order_item_moves'))->toBeTrue()
        ->and(Schema::hasColumns('order_item_moves', [
            'id',
            'tenant_id',
            'branch_id',
            'order_item_id',
            'source_order_id',
            'target_order_id',
            'source_subtable_id',
            'target_subtable_id',
            'actor_id',
            'reason',
            'created_at',
            'updated_at',
        ]))->toBeTrue()
        ->and(class_uses_recursive(Order::class))->toContain(BelongsToTenant::class)
        ->and(class_uses_recursive(OrderSubtable::class))->toContain(BelongsToTenant::class)
        ->and(class_uses_recursive(OrderItem::class))->toContain(BelongsToTenant::class)
        ->and(class_uses_recursive(OrderItemMove::class))->toContain(BelongsToTenant::class)
        ->and(class_uses_recursive(Order::class))->not->toContain(SoftDeletes::class)
        ->and(class_uses_recursive(OrderSubtable::class))->not->toContain(SoftDeletes::class)
        ->and(class_uses_recursive(OrderItem::class))->not->toContain(SoftDeletes::class)
        ->and(class_uses_recursive(OrderItemMove::class))->not->toContain(SoftDeletes::class);

    $orderIndexNames = collect(Schema::getIndexes('orders'))
        ->pluck('name')
        ->all();
    $subtableIndexNames = collect(Schema::getIndexes('order_subtables'))
        ->pluck('name')
        ->all();
    $itemIndexNames = collect(Schema::getIndexes('order_items'))
        ->pluck('name')
        ->all();
    $itemMoveIndexNames = collect(Schema::getIndexes('order_item_moves'))
        ->pluck('name')
        ->all();

    expect($orderIndexNames)->toContain('orders_tenant_id_index')
        ->and($orderIndexNames)->toContain('orders_branch_id_index')
        ->and($orderIndexNames)->toContain('orders_table_id_index')
        ->and($orderIndexNames)->toContain('orders_customer_id_index')
        ->and($orderIndexNames)->toContain('orders_waiter_id_index')
        ->and($orderIndexNames)->toContain('orders_cashier_id_index')
        ->and($orderIndexNames)->toContain('orders_tenant_branch_status_opened_id_idx')
        ->and($orderIndexNames)->toContain('orders_tenant_branch_table_status_idx')
        ->and($subtableIndexNames)->toContain('order_subtables_tenant_id_index')
        ->and($subtableIndexNames)->toContain('order_subtables_branch_id_index')
        ->and($subtableIndexNames)->toContain('order_subtables_order_id_index')
        ->and($subtableIndexNames)->toContain('order_subtables_tenant_branch_order_status_idx')
        ->and($itemIndexNames)->toContain('order_items_tenant_id_index')
        ->and($itemIndexNames)->toContain('order_items_branch_id_index')
        ->and($itemIndexNames)->toContain('order_items_order_id_index')
        ->and($itemIndexNames)->toContain('order_items_subtable_id_index')
        ->and($itemIndexNames)->toContain('order_items_menu_item_id_index')
        ->and($itemIndexNames)->toContain('order_items_seller_id_index')
        ->and($itemIndexNames)->toContain('order_items_tenant_branch_order_status_item_idx')
        ->and($itemIndexNames)->toContain('order_items_tenant_branch_menu_item_idx')
        ->and($itemIndexNames)->toContain('order_items_order_subtable_menu_price_idx')
        ->and($itemMoveIndexNames)->toContain('order_item_moves_tenant_id_index')
        ->and($itemMoveIndexNames)->toContain('order_item_moves_branch_id_index')
        ->and($itemMoveIndexNames)->toContain('order_item_moves_order_item_id_index')
        ->and($itemMoveIndexNames)->toContain('order_item_moves_source_order_id_index')
        ->and($itemMoveIndexNames)->toContain('order_item_moves_target_order_id_index')
        ->and($itemMoveIndexNames)->toContain('order_item_moves_source_subtable_id_index')
        ->and($itemMoveIndexNames)->toContain('order_item_moves_target_subtable_id_index')
        ->and($itemMoveIndexNames)->toContain('order_item_moves_actor_id_index')
        ->and($itemMoveIndexNames)->toContain('order_item_moves_tenant_branch_item_idx')
        ->and($itemMoveIndexNames)->toContain('order_item_moves_tenant_branch_source_target_idx');
});

it('creates PostgreSQL check constraints and order indexes', function (): void {
    if (Schema::getConnection()->getDriverName() !== 'pgsql') {
        expect(true)->toBeTrue();

        return;
    }

    $constraints = collect(DB::select(<<<'SQL'
        select conname, contype, pg_get_constraintdef(oid) as definition
        from pg_constraint
        where conrelid in ('orders'::regclass, 'order_subtables'::regclass, 'order_items'::regclass)
        SQL))
        ->mapWithKeys(fn (stdClass $constraint): array => [
            (string) $constraint->conname => [
                'type' => (string) $constraint->contype,
                'definition' => (string) $constraint->definition,
            ],
        ]);

    $indexes = collect(DB::select("select indexname, indexdef from pg_indexes where schemaname = 'public' and tablename = 'orders'"))
        ->mapWithKeys(fn (stdClass $index): array => [(string) $index->indexname => (string) $index->indexdef]);

    expect($constraints->get('orders_type_chk')['type'] ?? null)->toBe('c')
        ->and($constraints->get('orders_type_chk')['definition'] ?? '')->toContain('dine_in')
        ->and($constraints->get('orders_status_chk')['definition'] ?? '')->toContain('cancelled')
        ->and($constraints->get('orders_table_type_chk')['definition'] ?? '')->toContain('table_id IS NOT NULL')
        ->and($constraints->get('orders_table_type_chk')['definition'] ?? '')->toContain('table_id IS NULL')
        ->and($constraints->get('order_subtables_status_chk')['definition'] ?? '')->toContain('closed')
        ->and($constraints->get('order_items_qty_chk')['definition'] ?? '')->toContain('qty >= 1')
        ->and($constraints->get('order_items_preparation_status_chk')['definition'] ?? '')->toContain('pending')
        ->and($indexes->get('orders_one_open_dine_in_per_table_idx'))
        ->toContain('UNIQUE')
        ->toContain("WHERE (((status)::text = 'open'::text) AND ((type)::text = 'dine_in'::text))");
});
