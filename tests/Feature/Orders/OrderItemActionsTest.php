<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Models\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Identity\Infrastructure\Models\UserBranchAssignment;
use App\Modules\Menu\Contracts\MenuCatalog;
use App\Modules\Menu\Contracts\MenuItemSummary;
use App\Modules\Menu\Infrastructure\Models\MenuCategory;
use App\Modules\Menu\Infrastructure\Models\MenuItem;
use App\Modules\Orders\Application\AddItem;
use App\Modules\Orders\Application\AddSubtable;
use App\Modules\Orders\Application\CancelOrder;
use App\Modules\Orders\Application\ChangeItemQty;
use App\Modules\Orders\Application\OpenOrder;
use App\Modules\Orders\Application\RemoveItem;
use App\Modules\Orders\Domain\OrdersDomainException;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Orders\Infrastructure\Models\OrderItem;
use App\Modules\Tables\Infrastructure\Models\Hall;
use App\Modules\Tables\Infrastructure\Models\Table;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Modules\Tenancy\Infrastructure\Models\Branch;
use App\Modules\Tenancy\Infrastructure\Models\Tenant;
use App\Support\Audit\AuditLog;
use App\Support\I18n\LocalizedText;
use App\Support\Logging\LogContext;
use App\Support\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

afterEach(function (): void {
    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();
    LogContext::clear();
});

it('resolves one sellable menu item through the MenuCatalog boundary only', function (): void {
    $record = orderItemsUser('tenant-a', 'manager-a', branchCount: 2);
    $sellable = orderItemsMenuItem($record, 0, 'Dolma', 1200);
    $inactive = orderItemsMenuItem($record, 0, 'Hidden', 1300, active: false);
    $otherBranch = orderItemsMenuItem($record, 1, 'Other branch', 1400);

    app(TenantResolver::class)->set((int) $record['tenant']->id);
    app(BranchContext::class)->set((int) $record['branches'][0]->id);

    $summary = app(MenuCatalog::class)->findSellableInBranch((int) $sellable->id, (int) $record['branches'][0]->id);

    expect($summary)->toBeInstanceOf(MenuItemSummary::class)
        ->and($summary?->id)->toBe((int) $sellable->id)
        ->and($summary?->branchId)->toBe((int) $record['branches'][0]->id)
        ->and($summary?->name)->toBeInstanceOf(LocalizedText::class)
        ->and($summary?->price)->toBeInstanceOf(Money::class)
        ->and($summary?->price->minor)->toBe(1200)
        ->and(app(MenuCatalog::class)->findSellableInBranch((int) $inactive->id, (int) $record['branches'][0]->id))->toBeNull()
        ->and(app(MenuCatalog::class)->findSellableInBranch((int) $otherBranch->id, (int) $record['branches'][0]->id))->toBeNull();

    $sellable->delete();

    expect(app(MenuCatalog::class)->findSellableInBranch((int) $sellable->id, (int) $record['branches'][0]->id))->toBeNull();
});

it('adds increments changes and removes order items while keeping totals exact', function (): void {
    $record = orderItemsUser('tenant-a', 'manager-a');
    $table = orderItemsTable($record, 0, 'Table 1');
    $dolma = orderItemsMenuItem($record, 0, 'Dolma', 1000);
    $tan = orderItemsMenuItem($record, 0, 'Tan', 2500);

    orderItemsActingIn($record, 0, 'orders-items-request');
    $order = app(OpenOrder::class)((int) $table->id);
    $subtable = app(AddSubtable::class)((int) $order->id, 'Guest 1');

    $line = app(AddItem::class)((int) $order->id, (int) $dolma->id, 2);

    expect((int) $line->qty)->toBe(2)
        ->and((int) $line->unit_price_minor)->toBe(1000)
        ->and((int) $line->discount_minor)->toBe(0)
        ->and((int) $line->total_minor)->toBe(2000)
        ->and($line->currency)->toBe('AMD')
        ->and((int) $line->seller_id)->toBe((int) $record['user']->id)
        ->and($line->preparation_status)->toBe('pending')
        ->and(Order::query()->findOrFail((int) $order->id)->subtotal_minor)->toBe(2000)
        ->and(Order::query()->findOrFail((int) $order->id)->total_minor)->toBe(2000);

    $incremented = app(AddItem::class)((int) $order->id, (int) $dolma->id, 3);
    $secondLine = app(AddItem::class)((int) $order->id, (int) $tan->id, 2, (int) $subtable->id);

    expect($incremented->id)->toBe((int) $line->id)
        ->and((int) $incremented->qty)->toBe(5)
        ->and((int) $incremented->total_minor)->toBe(5000)
        ->and((int) $secondLine->subtable_id)->toBe((int) $subtable->id)
        ->and((int) $secondLine->total_minor)->toBe(5000)
        ->and(Order::query()->findOrFail((int) $order->id)->subtotal_minor)->toBe(10000)
        ->and(Order::query()->findOrFail((int) $order->id)->total_minor)->toBe(10000);

    $changed = app(ChangeItemQty::class)((int) $secondLine->id, 1);

    expect((int) $changed->qty)->toBe(1)
        ->and((int) $changed->total_minor)->toBe(2500)
        ->and(Order::query()->findOrFail((int) $order->id)->subtotal_minor)->toBe(7500)
        ->and(Order::query()->findOrFail((int) $order->id)->total_minor)->toBe(7500);

    $remainingOrder = app(RemoveItem::class)((int) $incremented->id);

    expect(OrderItem::query()->whereKey((int) $incremented->id)->exists())->toBeFalse()
        ->and((int) $remainingOrder->subtotal_minor)->toBe(2500)
        ->and((int) $remainingOrder->total_minor)->toBe(2500)
        ->and(OrderItem::query()->where('order_id', (int) $order->id)->sum('total_minor'))->toBe(2500);

    expect(AuditLog::query()->where('target_type', 'orders_item')->orderBy('id')->pluck('action')->all())->toBe([
        'orders.item.added',
        'orders.item.added',
        'orders.item.added',
        'orders.item.qty_changed',
        'orders.item.removed',
    ]);
});

it('rejects invalid item mutations with stable domain codes', function (): void {
    $record = orderItemsUser('tenant-a', 'manager-a', branchCount: 2);
    $table = orderItemsTable($record, 0, 'Table 1');
    $foreignSubtableTable = orderItemsTable($record, 1, 'Branch 2 Table');
    $item = orderItemsMenuItem($record, 0, 'Dolma', 1000);
    $usdItem = orderItemsMenuItem($record, 0, 'USD Item', 1000, 'USD');
    $foreignBranchItem = orderItemsMenuItem($record, 1, 'Foreign Item', 1000);

    orderItemsActingIn($record, 0, 'orders-items-guards');
    $order = app(OpenOrder::class)((int) $table->id);
    $line = app(AddItem::class)((int) $order->id, (int) $item->id, 1);

    orderItemsActingIn($record, 1, 'orders-items-foreign-subtable');
    $foreignOrder = app(OpenOrder::class)((int) $foreignSubtableTable->id);
    $foreignSubtable = app(AddSubtable::class)((int) $foreignOrder->id, 'Foreign Guest');

    orderItemsActingIn($record, 0, 'orders-items-guards');

    foreach ([
        [fn () => app(AddItem::class)((int) $order->id, (int) $item->id, 0), 'orders.invalid_quantity'],
        [fn () => app(ChangeItemQty::class)((int) $line->id, 0), 'orders.invalid_quantity'],
        [fn () => app(AddItem::class)((int) $order->id, (int) $foreignBranchItem->id, 1), 'orders.menu_item_not_found'],
        [fn () => app(AddItem::class)((int) $order->id, (int) $usdItem->id, 1), 'orders.currency_mismatch'],
        [fn () => app(AddItem::class)((int) $order->id, (int) $item->id, 1, (int) $foreignSubtable->id), 'orders.subtable_not_in_order'],
    ] as [$callback, $code]) {
        try {
            $callback();
        } catch (OrdersDomainException $exception) {
            expect($exception->errorCode())->toBe($code);

            continue;
        }

        throw new RuntimeException("Expected {$code}.");
    }

    app(CancelOrder::class)((int) $order->id);

    foreach ([
        fn () => app(AddItem::class)((int) $order->id, (int) $item->id, 1),
        fn () => app(ChangeItemQty::class)((int) $line->id, 2),
        fn () => app(RemoveItem::class)((int) $line->id),
    ] as $callback) {
        try {
            $callback();
        } catch (OrdersDomainException $exception) {
            expect($exception->errorCode())->toBe('orders.order_not_open');

            continue;
        }

        throw new RuntimeException('Expected open-order guard to throw.');
    }
});

it('keeps order items tenant scoped', function (): void {
    $tenantA = orderItemsUser('tenant-a', 'manager-a');
    $tenantB = orderItemsUser('tenant-b', 'manager-b');
    $tableA = orderItemsTable($tenantA, 0, 'Tenant A Table');
    $tableB = orderItemsTable($tenantB, 0, 'Tenant B Table');
    $menuA = orderItemsMenuItem($tenantA, 0, 'Tenant A Item', 1000);
    $menuB = orderItemsMenuItem($tenantB, 0, 'Tenant B Item', 2000);

    orderItemsActingIn($tenantA, 0, 'orders-items-tenant-a');
    $orderA = app(OpenOrder::class)((int) $tableA->id);
    $itemA = app(AddItem::class)((int) $orderA->id, (int) $menuA->id, 1);

    orderItemsActingIn($tenantB, 0, 'orders-items-tenant-b');
    $orderB = app(OpenOrder::class)((int) $tableB->id);
    $itemB = app(AddItem::class)((int) $orderB->id, (int) $menuB->id, 1);

    app(TenantResolver::class)->set((int) $tenantA['tenant']->id);
    app(BranchContext::class)->set((int) $tenantA['branches'][0]->id);

    expect(OrderItem::query()->pluck('id')->all())->toBe([(int) $itemA->id])
        ->and(OrderItem::query()->find((int) $itemB->id))->toBeNull();

    app(TenantResolver::class)->clear();

    expect(OrderItem::query()->count())->toBe(0);
});

/**
 * @return array{tenant: Tenant, branches: list<Branch>, user: User}
 */
function orderItemsUser(string $tenantSlug, string $username, int $branchCount = 1, string $currency = 'AMD'): array
{
    $tenant = Tenant::query()->create([
        'name' => str($tenantSlug)->headline()->toString(),
        'slug' => $tenantSlug,
        'default_locale' => 'en',
        'currency' => $currency,
        'status' => 'active',
    ]);

    app(TenantResolver::class)->set((int) $tenant->id);

    $branches = [];
    for ($index = 1; $index <= $branchCount; $index++) {
        $branches[] = Branch::query()->create([
            'name' => "{$tenantSlug} Branch {$index}",
            'timezone' => 'Asia/Yerevan',
            'status' => 'active',
        ]);
    }

    app(BranchContext::class)->set((int) $branches[0]->id);

    $role = Role::query()->create([
        'code' => "{$username}-role",
        'name' => "{$username} Role",
    ]);

    $user = User::query()->create([
        'role_id' => (int) $role->id,
        'name' => $username,
        'email' => "{$username}@smartrest.test",
        'username' => $username,
        'default_locale' => 'en',
        'active' => true,
        'is_superadmin' => false,
        'password' => Hash::make('password'),
    ]);

    foreach ($branches as $branch) {
        UserBranchAssignment::query()->create([
            'user_id' => (int) $user->id,
            'branch_id' => (int) $branch->id,
        ]);
    }

    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();

    return [
        'tenant' => $tenant,
        'branches' => $branches,
        'user' => $user,
    ];
}

/**
 * @param  array{tenant: Tenant, branches: list<Branch>, user: User}  $record
 */
function orderItemsTable(array $record, int $branchIndex, string $name): Table
{
    app(TenantResolver::class)->set((int) $record['tenant']->id);
    app(BranchContext::class)->set((int) $record['branches'][$branchIndex]->id);

    $hall = Hall::query()->create([
        'branch_id' => (int) $record['branches'][$branchIndex]->id,
        'translated_name' => ['hy' => "{$name} Hall", 'ru' => "{$name} Hall", 'en' => "{$name} Hall"],
        'color' => '#5FA8D3',
        'sort_order' => 10,
        'active' => true,
    ]);

    $table = Table::query()->create([
        'branch_id' => (int) $record['branches'][$branchIndex]->id,
        'hall_id' => (int) $hall->id,
        'translated_name' => ['hy' => $name, 'ru' => $name, 'en' => $name],
        'type' => 'standard',
        'shape' => 'square',
        'hdm_department' => 1,
        'is_delivery' => false,
        'sort_order' => 10,
        'active' => true,
    ]);

    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();

    return $table;
}

/**
 * @param  array{tenant: Tenant, branches: list<Branch>, user: User}  $record
 */
function orderItemsMenuItem(array $record, int $branchIndex, string $name, int $priceMinor, string $currency = 'AMD', bool $active = true): MenuItem
{
    app(TenantResolver::class)->set((int) $record['tenant']->id);
    app(BranchContext::class)->set((int) $record['branches'][$branchIndex]->id);

    $root = MenuCategory::query()->create([
        'translated_name' => ['hy' => "{$name} Root", 'ru' => "{$name} Root", 'en' => "{$name} Root"],
        'sort_order' => 0,
        'active' => true,
    ]);

    $category = MenuCategory::query()->create([
        'parent_id' => (int) $root->id,
        'translated_name' => ['hy' => "{$name} Category", 'ru' => "{$name} Category", 'en' => "{$name} Category"],
        'sort_order' => 10,
        'active' => true,
    ]);

    $item = MenuItem::query()->create([
        'branch_id' => (int) $record['branches'][$branchIndex]->id,
        'category_id' => (int) $category->id,
        'translated_name' => ['hy' => $name, 'ru' => $name, 'en' => $name],
        'translated_description' => ['hy' => "{$name} Description", 'ru' => "{$name} Description", 'en' => "{$name} Description"],
        'price_minor' => $priceMinor,
        'currency' => $currency,
        'active' => $active,
    ]);

    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();

    return $item;
}

/**
 * @param  array{tenant: Tenant, branches: list<Branch>, user: User}  $record
 */
function orderItemsActingIn(array $record, int $branchIndex, string $requestId): void
{
    app(TenantResolver::class)->set((int) $record['tenant']->id);
    app(BranchContext::class)->set((int) $record['branches'][$branchIndex]->id);
    auth()->login($record['user']);
    LogContext::start($requestId, 'orders');
}
