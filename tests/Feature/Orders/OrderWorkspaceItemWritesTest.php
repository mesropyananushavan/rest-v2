<?php

declare(strict_types=1);

use App\Livewire\Admin\OrderWorkspace as OrderWorkspaceComponent;
use App\Modules\Identity\Infrastructure\Models\Permission;
use App\Modules\Identity\Infrastructure\Models\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Identity\Infrastructure\Models\UserBranchAssignment;
use App\Modules\Menu\Application\CreateMenuCategory;
use App\Modules\Menu\Application\CreateMenuItem;
use App\Modules\Menu\Infrastructure\Models\MenuCategory;
use App\Modules\Menu\Infrastructure\Models\MenuItem;
use App\Modules\Orders\Application\AddItem;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Orders\Infrastructure\Models\OrderItem;
use App\Modules\Orders\Infrastructure\Models\OrderSubtable;
use App\Modules\Tables\Infrastructure\Models\Hall;
use App\Modules\Tables\Infrastructure\Models\Table;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Modules\Tenancy\Infrastructure\Models\Branch;
use App\Modules\Tenancy\Infrastructure\Models\Tenant;
use App\Support\I18n\LocalizedText;
use App\Support\Logging\LogContext;
use App\Support\Money\Money;
use App\Support\Money\MoneyFormatter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

afterEach(function (): void {
    app()->setLocale('hy');
    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();
    LogContext::clear();
});

it('adds sellable menu items to the selected workspace target and merges repeated adds', function (): void {
    $record = orderWorkspaceWritesUser('tenant-a', 'waiter-a', ['orders.take']);

    app()->setLocale('en');
    orderWorkspaceWritesActingIn($record, 0, 'workspace-writes-add');
    $order = orderWorkspaceWritesOrder($record, 0);
    $subtable = orderWorkspaceWritesSubtable($order, 'Guest A');
    $category = orderWorkspaceWritesCategory('Dining Menu', 'Hot Dishes')['category'];
    $dolma = orderWorkspaceWritesItem($category, $record['branches'][0], 'Dolma', priceMinor: 1000);
    $tan = orderWorkspaceWritesItem($category, $record['branches'][0], 'Tan', priceMinor: 500);

    Livewire::actingAs($record['user'])
        ->test(OrderWorkspaceComponent::class, ['orderId' => (int) $order->id])
        ->assertSee(__('orders.workspace.menu_picker.target_subtable_label'), false)
        ->set('targetSubtableId', (string) $subtable->id)
        ->call('addMenuItem', (int) $dolma->id)
        ->assertSet('statusMessage', __('orders.flash.item_added'))
        ->assertSee('Dolma', false)
        ->assertSee('Guest A', false)
        ->call('addMenuItem', (int) $dolma->id)
        ->assertSet('statusMessage', __('orders.flash.item_added'))
        ->set('targetSubtableId', '')
        ->call('addMenuItem', (int) $tan->id)
        ->assertSee(__('orders.workspace.unassigned_items'), false)
        ->assertSee('Tan', false)
        ->assertSee(MoneyFormatter::format(new Money(2500, 'AMD'), 'en'), false);

    $lines = OrderItem::query()
        ->where('order_id', (int) $order->id)
        ->orderBy('id')
        ->get();
    $freshOrder = Order::query()->findOrFail((int) $order->id);

    expect($lines)->toHaveCount(2)
        ->and((int) $lines[0]->menu_item_id)->toBe((int) $dolma->id)
        ->and((int) $lines[0]->subtable_id)->toBe((int) $subtable->id)
        ->and((int) $lines[0]->qty)->toBe(2)
        ->and((int) $lines[0]->total_minor)->toBe(2000)
        ->and((int) $lines[1]->menu_item_id)->toBe((int) $tan->id)
        ->and($lines[1]->subtable_id)->toBeNull()
        ->and((int) $freshOrder->subtotal_minor)->toBe(2500)
        ->and((int) $freshOrder->total_minor)->toBe(2500);
});

it('changes quantities and removes a line only through the confirmed remove action', function (): void {
    $record = orderWorkspaceWritesUser('tenant-a', 'waiter-a', ['orders.take']);

    app()->setLocale('en');
    orderWorkspaceWritesActingIn($record, 0, 'workspace-writes-qty-remove');
    $order = orderWorkspaceWritesOrder($record, 0);
    $category = orderWorkspaceWritesCategory('Dining Menu', 'Hot Dishes')['category'];
    $item = orderWorkspaceWritesItem($category, $record['branches'][0], 'Harissa', priceMinor: 1000);
    $line = app(AddItem::class)((int) $order->id, (int) $item->id, 2);

    Livewire::actingAs($record['user'])
        ->test(OrderWorkspaceComponent::class, ['orderId' => (int) $order->id])
        ->assertSee('remove_order_item_'.((int) $line->id), false)
        ->assertDontSee('<form', false)
        ->assertDontSee('wire:submit', false)
        ->call('increaseItemQty', (int) $line->id)
        ->assertSet('statusMessage', __('orders.flash.item_qty_changed'))
        ->assertSee(MoneyFormatter::format(new Money(3000, 'AMD'), 'en'), false)
        ->call('decreaseItemQty', (int) $line->id)
        ->call('decreaseItemQty', (int) $line->id)
        ->assertSet('statusMessage', __('orders.flash.item_qty_changed'))
        ->assertSee('disabled', false)
        ->call('decreaseItemQty', (int) $line->id)
        ->assertSet('errorMessage', __('orders.invalid_quantity'))
        ->call('confirmRemoveItem', (int) $line->id)
        ->assertSet('statusMessage', __('orders.flash.item_removed'))
        ->assertDontSee('remove_order_item_'.((int) $line->id), false)
        ->assertSee(MoneyFormatter::format(new Money(0, 'AMD'), 'en'), false);

    expect(OrderItem::query()->whereKey((int) $line->id)->exists())->toBeFalse()
        ->and((int) Order::query()->findOrFail((int) $order->id)->total_minor)->toBe(0);
});

it('keeps the workspace mutation boundary limited to item add quantity and remove controls', function (): void {
    $record = orderWorkspaceWritesUser('tenant-a', 'waiter-a', ['orders.take']);

    app()->setLocale('en');
    orderWorkspaceWritesActingIn($record, 0, 'workspace-writes-boundary');
    $order = orderWorkspaceWritesOrder($record, 0);
    $category = orderWorkspaceWritesCategory('Dining Menu', 'Hot Dishes')['category'];
    orderWorkspaceWritesItem($category, $record['branches'][0], 'Khash', priceMinor: 1000);

    Livewire::actingAs($record['user'])
        ->test(OrderWorkspaceComponent::class, ['orderId' => (int) $order->id])
        ->assertSee('addMenuItem', false)
        ->assertDontSee(__('orders.workspace.menu_picker.target_subtable_label'), false)
        ->assertDontSee('<form', false)
        ->assertDontSee('wire:submit', false)
        ->assertDontSee('type="number"', false)
        ->assertDontSee('AddItem', false)
        ->assertDontSee('openOrder', false)
        ->assertDontSee(__('orders.board.action_open'), false)
        ->assertDontSee('addSubtable', false)
        ->assertDontSee('moveItem', false)
        ->assertDontSee('moveOrder', false)
        ->assertDontSee('assignWaiter', false)
        ->assertDontSee('cancelOrder', false)
        ->assertDontSee('applyDiscount', false)
        ->assertDontSee('discountOrder', false)
        ->assertDontSee('discountItem', false)
        ->assertDontSee('payment', false)
        ->assertDontSee('closeOrder', false);
});

it('returns translated errors for unauthorized or out of scope mutation attempts', function (): void {
    $tenantA = orderWorkspaceWritesUser('tenant-a', 'waiter-a', ['orders.take'], branchCount: 2);
    $tenantB = orderWorkspaceWritesUser('tenant-b', 'waiter-b', ['orders.take']);
    $denied = orderWorkspaceWritesUser('tenant-c', 'viewer-c', []);

    orderWorkspaceWritesActingIn($tenantA, 0, 'workspace-writes-scope-a');
    $order = orderWorkspaceWritesOrder($tenantA, 0);
    $category = orderWorkspaceWritesCategory('Tenant A Menu', 'Tenant A Category')['category'];
    $visibleItem = orderWorkspaceWritesItem($category, $tenantA['branches'][0], 'Visible Dish');

    orderWorkspaceWritesActingIn($tenantA, 1, 'workspace-writes-scope-other-branch');
    $otherBranchOrder = orderWorkspaceWritesOrder($tenantA, 1);
    $otherBranchCategory = orderWorkspaceWritesCategory('Other Branch Menu', 'Other Branch Category')['category'];
    $otherBranchItem = orderWorkspaceWritesItem($otherBranchCategory, $tenantA['branches'][1], 'Other Branch Dish');
    $otherBranchLine = app(AddItem::class)((int) $otherBranchOrder->id, (int) $otherBranchItem->id, 1);

    orderWorkspaceWritesActingIn($tenantB, 0, 'workspace-writes-scope-b');
    $foreignCategory = orderWorkspaceWritesCategory('Tenant B Menu', 'Tenant B Category')['category'];
    $foreignItem = orderWorkspaceWritesItem($foreignCategory, $tenantB['branches'][0], 'Foreign Dish');

    orderWorkspaceWritesActingIn($tenantA, 0, 'workspace-writes-scope-render');

    Livewire::actingAs($denied['user'])
        ->test(OrderWorkspaceComponent::class, ['orderId' => (int) $order->id])
        ->assertStatus(403);

    Livewire::actingAs($tenantA['user'])
        ->test(OrderWorkspaceComponent::class, ['orderId' => (int) $order->id])
        ->call('addMenuItem', (int) $foreignItem->id)
        ->assertSet('errorMessage', __('orders.menu_item_not_found'))
        ->call('addMenuItem', (int) $otherBranchItem->id)
        ->assertSet('errorMessage', __('orders.menu_item_not_found'))
        ->call('increaseItemQty', (int) $otherBranchLine->id)
        ->assertSet('errorMessage', __('orders.item_not_in_order'))
        ->call('confirmRemoveItem', (int) $otherBranchLine->id)
        ->assertSet('errorMessage', __('orders.item_not_in_order'));

    expect(OrderItem::query()->where('order_id', (int) $order->id)->count())->toBe(0)
        ->and($visibleItem)->toBeInstanceOf(MenuItem::class);
});

it('rejects client supplied target subtable ids that do not belong to the order', function (): void {
    $tenantA = orderWorkspaceWritesUser('tenant-a', 'waiter-a', ['orders.take'], branchCount: 2);
    $tenantB = orderWorkspaceWritesUser('tenant-b', 'waiter-b', ['orders.take']);

    app()->setLocale('en');
    orderWorkspaceWritesActingIn($tenantA, 0, 'workspace-writes-subtable-main');
    $order = orderWorkspaceWritesOrder($tenantA, 0);
    $category = orderWorkspaceWritesCategory('Dining Menu', 'Hot Dishes')['category'];
    $item = orderWorkspaceWritesItem($category, $tenantA['branches'][0], 'Ghapama', priceMinor: 1000);

    $sameBranchOtherOrder = orderWorkspaceWritesOrder($tenantA, 0);
    $sameBranchSubtable = orderWorkspaceWritesSubtable($sameBranchOtherOrder, 'Other order guest');

    orderWorkspaceWritesActingIn($tenantA, 1, 'workspace-writes-subtable-other-branch');
    $otherBranchOrder = orderWorkspaceWritesOrder($tenantA, 1);
    $otherBranchSubtable = orderWorkspaceWritesSubtable($otherBranchOrder, 'Other branch guest');

    orderWorkspaceWritesActingIn($tenantB, 0, 'workspace-writes-subtable-other-tenant');
    $otherTenantOrder = orderWorkspaceWritesOrder($tenantB, 0);
    $otherTenantSubtable = orderWorkspaceWritesSubtable($otherTenantOrder, 'Other tenant guest');

    $missingSubtableId = ((int) max([
        $sameBranchSubtable->id,
        $otherBranchSubtable->id,
        $otherTenantSubtable->id,
    ])) + 10_000;

    orderWorkspaceWritesActingIn($tenantA, 0, 'workspace-writes-subtable-render');

    foreach ([
        'different order in same branch' => (int) $sameBranchSubtable->id,
        'different branch' => (int) $otherBranchSubtable->id,
        'different tenant' => (int) $otherTenantSubtable->id,
        'non-existent subtable' => $missingSubtableId,
    ] as $case => $subtableId) {
        Livewire::actingAs($tenantA['user'])
            ->test(OrderWorkspaceComponent::class, ['orderId' => (int) $order->id])
            ->set('targetSubtableId', (string) $subtableId)
            ->call('addMenuItem', (int) $item->id)
            ->assertSet('errorMessage', __('orders.subtable_not_in_order'));

        $freshOrder = Order::query()->findOrFail((int) $order->id);

        expect(OrderItem::query()->where('order_id', (int) $order->id)->count(), $case)->toBe(0)
            ->and((int) $freshOrder->subtotal_minor, $case)->toBe(0)
            ->and((int) $freshOrder->total_minor, $case)->toBe(0);
    }
});

it('keeps mounted component mutations safe after the order is closed or cancelled elsewhere', function (string $status): void {
    $record = orderWorkspaceWritesUser('tenant-a', 'waiter-a', ['orders.take']);

    app()->setLocale('en');
    orderWorkspaceWritesActingIn($record, 0, "workspace-writes-stale-{$status}");
    $order = orderWorkspaceWritesOrder($record, 0);
    $category = orderWorkspaceWritesCategory('Dining Menu', 'Hot Dishes')['category'];
    $existingItem = orderWorkspaceWritesItem($category, $record['branches'][0], 'Spas', priceMinor: 1000);
    $newItem = orderWorkspaceWritesItem($category, $record['branches'][0], 'Gata', priceMinor: 500);
    $line = app(AddItem::class)((int) $order->id, (int) $existingItem->id, 2);

    $component = Livewire::actingAs($record['user'])
        ->test(OrderWorkspaceComponent::class, ['orderId' => (int) $order->id]);

    $order->forceFill([
        'status' => $status,
        'closed_at' => now(),
    ])->save();

    $component
        ->call('addMenuItem', (int) $newItem->id)
        ->assertSet('errorMessage', __('orders.order_not_open'))
        ->call('increaseItemQty', (int) $line->id)
        ->assertSet('errorMessage', __('orders.order_not_open'))
        ->call('decreaseItemQty', (int) $line->id)
        ->assertSet('errorMessage', __('orders.order_not_open'))
        ->call('confirmRemoveItem', (int) $line->id)
        ->assertSet('errorMessage', __('orders.order_not_open'))
        ->assertSee(__('orders.workspace.unavailable_title'), false)
        ->assertDontSee('addMenuItem', false)
        ->assertDontSee('confirmRemoveItem', false)
        ->assertDontSee(__('orders.workspace.menu_picker.title'), false)
        ->assertDontSee(__('orders.workspace.summary_title'), false)
        ->assertDontSee('Spas', false)
        ->assertDontSee('Gata', false)
        ->assertDontSee(MoneyFormatter::format(new Money(2000, 'AMD'), 'en'), false);

    $freshLine = OrderItem::query()->findOrFail((int) $line->id);
    $freshOrder = Order::query()->findOrFail((int) $order->id);

    expect((string) $freshOrder->status)->toBe($status)
        ->and((int) $freshLine->qty)->toBe(2)
        ->and((int) $freshOrder->subtotal_minor)->toBe(2000)
        ->and((int) $freshOrder->total_minor)->toBe(2000)
        ->and(OrderItem::query()->where('order_id', (int) $order->id)->count())->toBe(1);
})->with(['closed', 'cancelled']);

it('renders translated errors when a line disappears before quantity or remove actions', function (): void {
    $record = orderWorkspaceWritesUser('tenant-a', 'waiter-a', ['orders.take']);

    app()->setLocale('en');
    orderWorkspaceWritesActingIn($record, 0, 'workspace-writes-concurrent-remove');
    $order = orderWorkspaceWritesOrder($record, 0);
    $category = orderWorkspaceWritesCategory('Dining Menu', 'Hot Dishes')['category'];
    $item = orderWorkspaceWritesItem($category, $record['branches'][0], 'Jengyalov hats', priceMinor: 1000);
    $line = app(AddItem::class)((int) $order->id, (int) $item->id, 1);

    $component = Livewire::actingAs($record['user'])
        ->test(OrderWorkspaceComponent::class, ['orderId' => (int) $order->id]);

    $line->delete();

    $component
        ->call('increaseItemQty', (int) $line->id)
        ->assertSet('errorMessage', __('orders.item_not_in_order'))
        ->call('confirmRemoveItem', (int) $line->id)
        ->assertSet('errorMessage', __('orders.item_not_in_order'));
});

it('preserves menu picker state after successful and failed add attempts', function (): void {
    $record = orderWorkspaceWritesUser('tenant-a', 'waiter-a', ['orders.take']);

    app()->setLocale('en');
    orderWorkspaceWritesActingIn($record, 0, 'workspace-writes-menu-state');
    $order = orderWorkspaceWritesOrder($record, 0);
    $validSubtable = orderWorkspaceWritesSubtable($order, 'Guest A');

    $selectedCategory = null;
    $selectedItem = null;
    $failedItem = null;

    for ($index = 1; $index <= 7; $index++) {
        $category = orderWorkspaceWritesCategory("State Menu {$index}", "State Category {$index}")['category'];
        $itemsForCategory = $index === 1 ? 14 : 1;

        for ($itemIndex = 1; $itemIndex <= $itemsForCategory; $itemIndex++) {
            $item = orderWorkspaceWritesItem(
                $category,
                $record['branches'][0],
                sprintf('State Dish %02d-%02d', $index, $itemIndex),
                priceMinor: 1000 + $itemIndex,
                sortOrder: $itemIndex,
            );

            if ($index === 1 && $itemIndex === 13) {
                $selectedCategory = $category;
                $selectedItem = $item;
            }

            if ($index === 1 && $itemIndex === 14) {
                $failedItem = $item;
            }
        }
    }

    expect($selectedCategory)->toBeInstanceOf(MenuCategory::class)
        ->and($selectedItem)->toBeInstanceOf(MenuItem::class)
        ->and($failedItem)->toBeInstanceOf(MenuItem::class);

    $component = Livewire::actingAs($record['user'])
        ->test(OrderWorkspaceComponent::class, ['orderId' => (int) $order->id])
        ->set('menuSearch', 'State Dish')
        ->call('selectMenuCategory', (int) $selectedCategory->id)
        ->call('nextMenuPage')
        ->call('nextMenuCategoryPage');

    $expectedState = [
        'menuSearch' => 'State Dish',
        'menuCategoryId' => (int) $selectedCategory->id,
        'menuPage' => 2,
        'menuCategoryPage' => 2,
    ];

    foreach ($expectedState as $property => $value) {
        $component->assertSet($property, $value);
    }

    $component
        ->set('targetSubtableId', (string) $validSubtable->id)
        ->call('addMenuItem', (int) $selectedItem->id)
        ->assertSet('statusMessage', __('orders.flash.item_added'));

    foreach ($expectedState as $property => $value) {
        $component->assertSet($property, $value);
    }

    $failedItem->forceFill(['active' => false])->save();

    $component
        ->call('addMenuItem', (int) $failedItem->id)
        ->assertSet('errorMessage', __('orders.menu_item_not_found'));

    foreach ($expectedState as $property => $value) {
        $component->assertSet($property, $value);
    }
});

it('keeps order translation key sets identical across supported locales', function (): void {
    $english = orderWorkspaceWritesFlattenKeys(require base_path('lang/en/orders.php'));
    $armenian = orderWorkspaceWritesFlattenKeys(require base_path('lang/hy/orders.php'));
    $russian = orderWorkspaceWritesFlattenKeys(require base_path('lang/ru/orders.php'));

    expect($armenian)->toBe($english)
        ->and($russian)->toBe($english);
});

it('keeps changed workspace Livewire expressions encoded and limited to identifiers', function (): void {
    $blade = file_get_contents(resource_path('views/livewire/admin/order-workspace.blade.php'));

    expect($blade)->toBeString()
        ->and($blade)->not->toMatch('/wire:click="[^"]*\{\{/')
        ->and($blade)->toContain('addMenuItem(@js($item[\'id\']))')
        ->and($blade)->toContain('increaseItemQty(@js($item[\'id\']))')
        ->and($blade)->toContain('decreaseItemQty(@js($item[\'id\']))')
        ->and($blade)->toContain(':livewire-arguments="[(int) $item[\'id\']]"')
        ->and($blade)->not->toContain('wire:submit');
});

it('keeps workspace render and mutation round trip query counts bounded', function (): void {
    $record = orderWorkspaceWritesUser('tenant-a', 'waiter-a', ['orders.take']);

    app()->setLocale('en');
    orderWorkspaceWritesActingIn($record, 0, 'workspace-writes-query-counts');
    $order = orderWorkspaceWritesOrder($record, 0);
    $category = orderWorkspaceWritesCategory('Query Menu', 'Query Category')['category'];

    for ($index = 1; $index <= 13; $index++) {
        orderWorkspaceWritesItem($category, $record['branches'][0], "Query Dish {$index}", priceMinor: 1000, sortOrder: $index);
    }

    $initialItem = orderWorkspaceWritesItem($category, $record['branches'][0], 'Existing Query Dish', priceMinor: 1000, sortOrder: 14);
    $line = app(AddItem::class)((int) $order->id, (int) $initialItem->id, 2);
    $addItem = orderWorkspaceWritesItem($category, $record['branches'][0], 'Added Query Dish', priceMinor: 1000, sortOrder: 15);

    [, $renderQueries] = orderWorkspaceWritesQueryCount(
        fn () => Livewire::actingAs($record['user'])->test(OrderWorkspaceComponent::class, ['orderId' => (int) $order->id]),
    );

    [$component, $addQueries] = orderWorkspaceWritesQueryCount(
        fn () => Livewire::actingAs($record['user'])
            ->test(OrderWorkspaceComponent::class, ['orderId' => (int) $order->id])
            ->call('addMenuItem', (int) $addItem->id),
    );

    [, $increaseQueries] = orderWorkspaceWritesQueryCount(
        fn () => $component->call('increaseItemQty', (int) $line->id),
    );

    [, $decreaseQueries] = orderWorkspaceWritesQueryCount(
        fn () => $component->call('decreaseItemQty', (int) $line->id),
    );

    [, $removeQueries] = orderWorkspaceWritesQueryCount(
        fn () => $component->call('confirmRemoveItem', (int) $line->id),
    );

    expect($renderQueries)->toBeLessThanOrEqual(20)
        ->and($addQueries)->toBeLessThanOrEqual(45)
        ->and($increaseQueries)->toBeLessThanOrEqual(35)
        ->and($decreaseQueries)->toBeLessThanOrEqual(35)
        ->and($removeQueries)->toBeLessThanOrEqual(35);
});

it('keeps add item query count stable as order lines and picked menu items grow', function (): void {
    $oneLineQueries = orderWorkspaceWritesAddRoundTripQueryCount(lineCount: 1, pickedItemCount: 10, label: 'one-line');
    $tenLineQueries = orderWorkspaceWritesAddRoundTripQueryCount(lineCount: 10, pickedItemCount: 10, label: 'ten-lines');
    $onePickedItemQueries = orderWorkspaceWritesAddRoundTripQueryCount(lineCount: 1, pickedItemCount: 1, label: 'one-picked-item');
    $tenPickedItemQueries = orderWorkspaceWritesAddRoundTripQueryCount(lineCount: 1, pickedItemCount: 10, label: 'ten-picked-items');

    expect($tenLineQueries)->toBe($oneLineQueries)
        ->and($onePickedItemQueries)->toBe($tenPickedItemQueries)
        ->and($tenLineQueries)->toBeLessThanOrEqual(45)
        ->and($tenPickedItemQueries)->toBeLessThanOrEqual(45);
});

/**
 * @param  list<string>  $permissionCodes
 * @return array{tenant: Tenant, branches: list<Branch>, user: User}
 */
function orderWorkspaceWritesUser(string $tenantSlug, string $username, array $permissionCodes, int $branchCount = 1): array
{
    $tenant = Tenant::query()->create([
        'name' => str($tenantSlug)->headline()->toString(),
        'slug' => $tenantSlug,
        'default_locale' => 'en',
        'currency' => 'AMD',
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

    $role = Role::query()->create([
        'code' => "{$username}-role",
        'name' => "{$username} Role",
    ]);

    $permissions = collect($permissionCodes)
        ->map(fn (string $code): Permission => Permission::query()->create([
            'code' => $code,
            'name' => $code,
        ]));

    if ($permissions->isNotEmpty()) {
        $role->permissions()->attach(
            $permissions->pluck('id')->all(),
            ['tenant_id' => (int) $tenant->id],
        );
    }

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
function orderWorkspaceWritesActingIn(array $record, int $branchIndex, string $requestId): void
{
    app(TenantResolver::class)->set((int) $record['tenant']->id);
    app(BranchContext::class)->set((int) $record['branches'][$branchIndex]->id);
    auth()->login($record['user']);
    LogContext::start($requestId, 'orders');
}

/**
 * @param  array{tenant: Tenant, branches: list<Branch>, user: User}  $record
 */
function orderWorkspaceWritesOrder(array $record, int $branchIndex): Order
{
    $table = orderWorkspaceWritesTable($record['branches'][$branchIndex]);

    return Order::query()->create([
        'branch_id' => (int) $table->branch_id,
        'type' => 'dine_in',
        'status' => 'open',
        'table_id' => (int) $table->id,
        'waiter_id' => (int) $record['user']->id,
        'opened_at' => now()->subMinutes(10),
        'closed_at' => null,
        'client_count' => 1,
        'comment' => null,
        'subtotal_minor' => 0,
        'discount_minor' => 0,
        'total_minor' => 0,
        'currency' => 'AMD',
    ]);
}

function orderWorkspaceWritesTable(Branch $branch): Table
{
    $hall = Hall::query()->create([
        'branch_id' => (int) $branch->id,
        'translated_name' => orderWorkspaceWritesTranslations('Writes Hall'),
        'color' => '#5FA8D3',
        'sort_order' => 10,
        'active' => true,
    ]);

    return Table::query()->create([
        'branch_id' => (int) $branch->id,
        'hall_id' => (int) $hall->id,
        'translated_name' => orderWorkspaceWritesTranslations('Writes Table'),
        'type' => 'standard',
        'shape' => 'square',
        'hdm_department' => 1,
        'is_delivery' => false,
        'sort_order' => 10,
        'active' => true,
    ]);
}

function orderWorkspaceWritesSubtable(Order $order, string $name): OrderSubtable
{
    return OrderSubtable::query()->create([
        'branch_id' => (int) $order->branch_id,
        'order_id' => (int) $order->id,
        'name' => $name,
        'status' => 'open',
    ]);
}

/**
 * @return array{root: MenuCategory, category: MenuCategory}
 */
function orderWorkspaceWritesCategory(string $rootName, string $categoryName): array
{
    $root = app(CreateMenuCategory::class)(orderWorkspaceWritesText($rootName));
    $category = app(CreateMenuCategory::class)(
        orderWorkspaceWritesText($categoryName),
        parentId: (int) $root->id,
    );

    return [
        'root' => $root,
        'category' => $category,
    ];
}

function orderWorkspaceWritesItem(
    MenuCategory $category,
    Branch $branch,
    string $en,
    int $priceMinor = 1000,
    int $sortOrder = 0,
): MenuItem {
    app(BranchContext::class)->set((int) $branch->id);

    return app(CreateMenuItem::class)(
        (int) $category->id,
        orderWorkspaceWritesText($en),
        null,
        new Money($priceMinor, 'AMD'),
        sortOrder: $sortOrder,
        active: true,
    );
}

function orderWorkspaceWritesText(string $en): LocalizedText
{
    return LocalizedText::fromArray(orderWorkspaceWritesTranslations($en));
}

/**
 * @return array{hy: string, ru: string, en: string}
 */
function orderWorkspaceWritesTranslations(string $text): array
{
    return [
        'hy' => $text,
        'ru' => $text,
        'en' => $text,
    ];
}

/**
 * @param  array<string, mixed>  $translations
 * @return list<string>
 */
function orderWorkspaceWritesFlattenKeys(array $translations, string $prefix = ''): array
{
    $keys = [];

    foreach ($translations as $key => $value) {
        $fullKey = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

        if (is_array($value)) {
            $keys = array_merge($keys, orderWorkspaceWritesFlattenKeys($value, $fullKey));

            continue;
        }

        $keys[] = $fullKey;
    }

    sort($keys);

    return $keys;
}

/**
 * @template TReturn
 *
 * @param  callable(): TReturn  $callback
 * @return array{0: TReturn, 1: int}
 */
function orderWorkspaceWritesQueryCount(callable $callback): array
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    try {
        $result = $callback();

        return [$result, count(DB::getQueryLog())];
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }
}

function orderWorkspaceWritesAddRoundTripQueryCount(int $lineCount, int $pickedItemCount, string $label): int
{
    $record = orderWorkspaceWritesUser("tenant-query-{$label}", "waiter-query-{$label}", ['orders.take']);

    app()->setLocale('en');
    orderWorkspaceWritesActingIn($record, 0, "workspace-writes-query-{$label}");
    $order = orderWorkspaceWritesOrder($record, 0);
    $pickedCategory = orderWorkspaceWritesCategory("Picked Menu {$label}", "Picked Category {$label}")['category'];
    $lineCategory = orderWorkspaceWritesCategory("Line Menu {$label}", "Line Category {$label}")['category'];
    $addItem = null;

    for ($index = 1; $index <= $pickedItemCount; $index++) {
        $item = orderWorkspaceWritesItem(
            $pickedCategory,
            $record['branches'][0],
            "Picked Dish {$label} {$index}",
            priceMinor: 1000,
            sortOrder: $index,
        );

        if ($addItem === null) {
            $addItem = $item;
        }
    }

    for ($index = 1; $index <= $lineCount; $index++) {
        $lineItem = orderWorkspaceWritesItem(
            $lineCategory,
            $record['branches'][0],
            "Line Dish {$label} {$index}",
            priceMinor: 1000 + $index,
            sortOrder: $index,
        );
        app(AddItem::class)((int) $order->id, (int) $lineItem->id, 1);
    }

    expect($addItem)->toBeInstanceOf(MenuItem::class);

    [, $queries] = orderWorkspaceWritesQueryCount(
        fn () => Livewire::withQueryParams(['menu_category' => (int) $pickedCategory->id])
            ->actingAs($record['user'])
            ->test(OrderWorkspaceComponent::class, ['orderId' => (int) $order->id])
            ->call('addMenuItem', (int) $addItem->id),
    );

    return $queries;
}
