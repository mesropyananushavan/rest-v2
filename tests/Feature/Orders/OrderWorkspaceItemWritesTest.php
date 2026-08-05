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
use App\Modules\Orders\Application\AddSubtable;
use App\Modules\Orders\Application\MoveItem;
use App\Modules\Orders\Domain\OrdersDomainException;
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

it('keeps the workspace mutation boundary limited to item add quantity remove subtable creation move waiter assignment and cancel controls', function (): void {
    $record = orderWorkspaceWritesUser('tenant-a', 'manager-a', ['orders.take', 'orders.cancel']);

    app()->setLocale('en');
    orderWorkspaceWritesActingIn($record, 0, 'workspace-writes-boundary');
    $order = orderWorkspaceWritesOrder($record, 0);
    $category = orderWorkspaceWritesCategory('Dining Menu', 'Hot Dishes')['category'];
    $menuItem = orderWorkspaceWritesItem($category, $record['branches'][0], 'Khash', priceMinor: 1000);
    app(AddItem::class)((int) $order->id, (int) $menuItem->id, 1);
    orderWorkspaceWritesSubtable($order, 'Guest A');

    Livewire::actingAs($record['user'])
        ->test(OrderWorkspaceComponent::class, ['orderId' => (int) $order->id])
        ->assertSee('addMenuItem', false)
        ->assertSee('createSubtable', false)
        ->assertSee('moveLineToSelectedSubtable', false)
        ->assertSee('assignWaiter', false)
        ->assertSee('clearWaiter', false)
        ->assertSee('cancelOrder()', false)
        ->assertSee(__('orders.workspace.menu_picker.target_subtable_label'), false)
        ->assertDontSee('<form', false)
        ->assertDontSee('wire:submit', false)
        ->assertDontSee('type="number"', false)
        ->assertDontSee('AddItem', false)
        ->assertDontSee('openOrder', false)
        ->assertDontSee(__('orders.board.action_open'), false)
        ->assertDontSee('moveOrder', false)
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

it('creates subtables through the workspace and renders validation messages', function (): void {
    $record = orderWorkspaceWritesUser('tenant-subtables', 'waiter-subtables', ['orders.take']);

    app()->setLocale('en');
    orderWorkspaceWritesActingIn($record, 0, 'workspace-subtables-create');
    $order = orderWorkspaceWritesOrder($record, 0);
    orderWorkspaceWritesSubtable($order, 'Guest A');

    $component = Livewire::actingAs($record['user'])
        ->test(OrderWorkspaceComponent::class, ['orderId' => (int) $order->id])
        ->set('newSubtableName', '')
        ->call('createSubtable')
        ->assertSet('errorMessage', __('orders.workspace.validation.subtable_name_required'))
        ->assertSee(__('orders.workspace.validation.subtable_name_required'), false)
        ->set('newSubtableName', '   ')
        ->call('createSubtable')
        ->assertSet('errorMessage', __('orders.workspace.validation.subtable_name_required'))
        ->assertSee(__('orders.workspace.validation.subtable_name_required'), false)
        ->set('newSubtableName', str_repeat('A', 61))
        ->call('createSubtable')
        ->assertSet('errorMessage', __('orders.workspace.validation.subtable_name_max', ['max' => 60]))
        ->assertSee(__('orders.workspace.validation.subtable_name_max', ['max' => 60]), false)
        ->set('newSubtableName', ' guest a ')
        ->call('createSubtable')
        ->assertSet('errorMessage', __('orders.workspace.validation.subtable_name_duplicate'))
        ->assertSee(__('orders.workspace.validation.subtable_name_duplicate'), false)
        ->set('newSubtableName', ' Guest B ')
        ->call('createSubtable')
        ->assertSet('statusMessage', __('orders.flash.subtable_added'))
        ->assertSet('newSubtableName', '')
        ->assertSee('Guest B', false);

    expect(OrderSubtable::query()->where('order_id', (int) $order->id)->pluck('name')->all())
        ->toBe(['Guest A', 'Guest B'])
        ->and(substr_count($component->html(), 'Guest B'))->toBeGreaterThanOrEqual(2);
});

it('maps application subtable validation domain errors in the workspace', function (): void {
    $record = orderWorkspaceWritesUser('tenant-subtable-domain-errors', 'waiter-subtable-domain-errors', ['orders.take']);

    app()->setLocale('en');
    orderWorkspaceWritesActingIn($record, 0, 'workspace-subtable-domain-errors');
    $order = orderWorkspaceWritesOrder($record, 0);

    foreach ([
        'required' => [
            fn (): OrdersDomainException => OrdersDomainException::subtableNameRequired(),
            __('orders.workspace.validation.subtable_name_required'),
        ],
        'too long' => [
            fn (): OrdersDomainException => OrdersDomainException::subtableNameTooLong(),
            __('orders.workspace.validation.subtable_name_max', ['max' => 60]),
        ],
        'duplicate' => [
            fn (): OrdersDomainException => OrdersDomainException::subtableNameDuplicate(),
            __('orders.workspace.validation.subtable_name_duplicate'),
        ],
    ] as $case => [$exceptionFactory, $message]) {
        app()->instance(AddSubtable::class, new class($exceptionFactory)
        {
            /**
             * @param  Closure(): OrdersDomainException  $exceptionFactory
             */
            public function __construct(
                private readonly Closure $exceptionFactory,
            ) {}

            public function __invoke(int $orderId, string $name): never
            {
                throw ($this->exceptionFactory)();
            }
        });

        try {
            Livewire::actingAs($record['user'])
                ->test(OrderWorkspaceComponent::class, ['orderId' => (int) $order->id])
                ->set('newSubtableName', 'Valid Guest')
                ->call('createSubtable')
                ->assertSet('errorMessage', $message)
                ->assertSee($message, false);
        } finally {
            app()->forgetInstance(AddSubtable::class);
        }

        expect(OrderSubtable::query()->where('order_id', (int) $order->id)->count(), $case)->toBe(0);
    }
});

it('moves a line from without subtable to a subtable and between subtables while excluding the current target', function (): void {
    $record = orderWorkspaceWritesUser('tenant-moves', 'waiter-moves', ['orders.take']);

    app()->setLocale('en');
    orderWorkspaceWritesActingIn($record, 0, 'workspace-moves-success');
    $order = orderWorkspaceWritesOrder($record, 0);
    $subtableA = orderWorkspaceWritesSubtable($order, 'Guest A');
    $subtableB = orderWorkspaceWritesSubtable($order, 'Guest B');
    $category = orderWorkspaceWritesCategory('Move Menu', 'Move Category')['category'];
    $menuItem = orderWorkspaceWritesItem($category, $record['branches'][0], 'Dolma', priceMinor: 1000);
    $line = app(AddItem::class)((int) $order->id, (int) $menuItem->id, 1);

    $component = Livewire::actingAs($record['user'])
        ->test(OrderWorkspaceComponent::class, ['orderId' => (int) $order->id]);

    $initialMoveSelect = orderWorkspaceWritesMoveSelectHtml($component->html(), (int) $line->id);

    expect($initialMoveSelect)->toContain('Guest A')
        ->and($initialMoveSelect)->toContain('Guest B')
        ->and($initialMoveSelect)->not->toContain(__('orders.workspace.unassigned_items'));

    $component
        ->set("moveTargetSubtableIds.{$line->id}", (string) $subtableA->id)
        ->call('moveLineToSelectedSubtable', (int) $line->id)
        ->assertSet('statusMessage', __('orders.flash.item_moved'))
        ->assertSee('Guest A', false);

    expect((int) OrderItem::query()->findOrFail((int) $line->id)->subtable_id)->toBe((int) $subtableA->id);

    $afterFirstMoveSelect = orderWorkspaceWritesMoveSelectHtml($component->html(), (int) $line->id);

    expect($afterFirstMoveSelect)->toContain(__('orders.workspace.unassigned_items'))
        ->and($afterFirstMoveSelect)->toContain('Guest B')
        ->and($afterFirstMoveSelect)->not->toContain('Guest A');

    $component
        ->set("moveTargetSubtableIds.{$line->id}", (string) $subtableB->id)
        ->call('moveLineToSelectedSubtable', (int) $line->id)
        ->assertSet('statusMessage', __('orders.flash.item_moved'));

    expect((int) OrderItem::query()->findOrFail((int) $line->id)->subtable_id)->toBe((int) $subtableB->id);

    $afterSecondMoveSelect = orderWorkspaceWritesMoveSelectHtml($component->html(), (int) $line->id);

    expect($afterSecondMoveSelect)->toContain(__('orders.workspace.unassigned_items'))
        ->and($afterSecondMoveSelect)->toContain('Guest A')
        ->and($afterSecondMoveSelect)->not->toContain('Guest B');
});

it('renders a translated item move noop when another cashier already moved the line', function (): void {
    $record = orderWorkspaceWritesUser('tenant-noop', 'waiter-noop', ['orders.take']);

    app()->setLocale('en');
    orderWorkspaceWritesActingIn($record, 0, 'workspace-move-noop');
    $order = orderWorkspaceWritesOrder($record, 0);
    $subtable = orderWorkspaceWritesSubtable($order, 'Guest A');
    $category = orderWorkspaceWritesCategory('Noop Menu', 'Noop Category')['category'];
    $menuItem = orderWorkspaceWritesItem($category, $record['branches'][0], 'Harissa', priceMinor: 1000);
    $line = app(AddItem::class)((int) $order->id, (int) $menuItem->id, 1);

    $component = Livewire::actingAs($record['user'])
        ->test(OrderWorkspaceComponent::class, ['orderId' => (int) $order->id])
        ->set("moveTargetSubtableIds.{$line->id}", (string) $subtable->id);

    app(MoveItem::class)((int) $line->id, null, (int) $subtable->id);

    $component
        ->call('moveLineToSelectedSubtable', (int) $line->id)
        ->assertSet('errorMessage', __('orders.item_move_noop'));
});

it('passes client supplied move target ids to MoveItem and renders translated rejections', function (): void {
    $tenantA = orderWorkspaceWritesUser('tenant-move-target-a', 'waiter-move-target-a', ['orders.take'], branchCount: 2);
    $tenantB = orderWorkspaceWritesUser('tenant-move-target-b', 'waiter-move-target-b', ['orders.take']);

    app()->setLocale('en');
    orderWorkspaceWritesActingIn($tenantA, 0, 'workspace-move-target-source');
    $order = orderWorkspaceWritesOrder($tenantA, 0);
    $category = orderWorkspaceWritesCategory('Target Menu', 'Target Category')['category'];
    $menuItem = orderWorkspaceWritesItem($category, $tenantA['branches'][0], 'Ghapama', priceMinor: 1000);
    $line = app(AddItem::class)((int) $order->id, (int) $menuItem->id, 1);

    $sameBranchOtherOrder = orderWorkspaceWritesOrder($tenantA, 0);
    $sameBranchSubtable = orderWorkspaceWritesSubtable($sameBranchOtherOrder, 'Other order');

    orderWorkspaceWritesActingIn($tenantA, 1, 'workspace-move-target-other-branch');
    $otherBranchOrder = orderWorkspaceWritesOrder($tenantA, 1);
    $otherBranchSubtable = orderWorkspaceWritesSubtable($otherBranchOrder, 'Other branch');

    orderWorkspaceWritesActingIn($tenantB, 0, 'workspace-move-target-other-tenant');
    $otherTenantOrder = orderWorkspaceWritesOrder($tenantB, 0);
    $otherTenantSubtable = orderWorkspaceWritesSubtable($otherTenantOrder, 'Other tenant');

    orderWorkspaceWritesActingIn($tenantA, 0, 'workspace-move-target-render');
    $missingSubtableId = ((int) max([
        $sameBranchSubtable->id,
        $otherBranchSubtable->id,
        $otherTenantSubtable->id,
    ])) + 10_000;

    foreach ([
        'different order in same branch' => (int) $sameBranchSubtable->id,
        'different branch' => (int) $otherBranchSubtable->id,
        'different tenant' => (int) $otherTenantSubtable->id,
        'non-existent subtable' => $missingSubtableId,
    ] as $case => $subtableId) {
        Livewire::actingAs($tenantA['user'])
            ->test(OrderWorkspaceComponent::class, ['orderId' => (int) $order->id])
            ->set("moveTargetSubtableIds.{$line->id}", (string) $subtableId)
            ->call('moveLineToSelectedSubtable', (int) $line->id)
            ->assertSet('errorMessage', __('orders.subtable_not_in_order'));

        $freshOrder = Order::query()->findOrFail((int) $order->id);
        $freshLine = OrderItem::query()->findOrFail((int) $line->id);

        expect($freshLine->subtable_id, $case)->toBeNull()
            ->and((int) $freshOrder->subtotal_minor, $case)->toBe(1000)
            ->and((int) $freshOrder->total_minor, $case)->toBe(1000);
    }
});

it('rechecks orders take permission in new mounted component mutations', function (string $method): void {
    $record = orderWorkspaceWritesUser("tenant-permission-{$method}", "waiter-permission-{$method}", ['orders.take']);

    app()->setLocale('en');
    orderWorkspaceWritesActingIn($record, 0, "workspace-permission-{$method}");
    $order = orderWorkspaceWritesOrder($record, 0);
    $subtable = orderWorkspaceWritesSubtable($order, 'Guest A');
    $category = orderWorkspaceWritesCategory("Permission Menu {$method}", "Permission Category {$method}")['category'];
    $menuItem = orderWorkspaceWritesItem($category, $record['branches'][0], "Permission Dish {$method}", priceMinor: 1000);
    $line = app(AddItem::class)((int) $order->id, (int) $menuItem->id, 1);

    $component = Livewire::actingAs($record['user'])
        ->test(OrderWorkspaceComponent::class, ['orderId' => (int) $order->id]);

    Role::query()
        ->findOrFail((int) $record['user']->role_id)
        ->permissions()
        ->detach();

    if ($method === 'create') {
        $component
            ->set('newSubtableName', 'Late Guest')
            ->call('createSubtable')
            ->assertStatus(403);
    } elseif ($method === 'move') {
        $component
            ->set("moveTargetSubtableIds.{$line->id}", (string) $subtable->id)
            ->call('moveLineToSelectedSubtable', (int) $line->id)
            ->assertStatus(403);
    } elseif ($method === 'assign') {
        $component
            ->set('selectedWaiterId', (string) $record['user']->id)
            ->call('assignWaiter')
            ->assertStatus(403);
    } else {
        $component
            ->call('clearWaiter')
            ->assertStatus(403);
    }
})->with(['create', 'move', 'assign', 'clear']);

it('keeps new mounted component mutations safe after the order is closed or cancelled elsewhere', function (string $status): void {
    $record = orderWorkspaceWritesUser("tenant-new-stale-{$status}", "waiter-new-stale-{$status}", ['orders.take']);

    app()->setLocale('en');
    orderWorkspaceWritesActingIn($record, 0, "workspace-new-stale-{$status}");
    $order = orderWorkspaceWritesOrder($record, 0);
    $subtable = orderWorkspaceWritesSubtable($order, 'Guest A');
    $category = orderWorkspaceWritesCategory("Stale Menu {$status}", "Stale Category {$status}")['category'];
    $menuItem = orderWorkspaceWritesItem($category, $record['branches'][0], "Stale Dish {$status}", priceMinor: 1000);
    $line = app(AddItem::class)((int) $order->id, (int) $menuItem->id, 1);

    $component = Livewire::actingAs($record['user'])
        ->test(OrderWorkspaceComponent::class, ['orderId' => (int) $order->id]);

    $order->forceFill([
        'status' => $status,
        'closed_at' => now(),
    ])->save();

    $component
        ->set('newSubtableName', 'Late Guest')
        ->call('createSubtable')
        ->assertSet('errorMessage', __('orders.order_not_open'))
        ->set("moveTargetSubtableIds.{$line->id}", (string) $subtable->id)
        ->call('moveLineToSelectedSubtable', (int) $line->id)
        ->assertSet('errorMessage', __('orders.order_not_open'))
        ->assertSee(__('orders.workspace.unavailable_title'), false)
        ->assertDontSee('createSubtable', false)
        ->assertDontSee('moveLineToSelectedSubtable', false)
        ->assertDontSee(__('orders.workspace.menu_picker.title'), false)
        ->assertDontSee("Stale Dish {$status}", false);

    $freshLine = OrderItem::query()->findOrFail((int) $line->id);

    expect(OrderSubtable::query()->where('order_id', (int) $order->id)->pluck('name')->all())
        ->toBe(['Guest A'])
        ->and($freshLine->subtable_id)->toBeNull();
})->with(['closed', 'cancelled']);

it('preserves menu picker state after successful and failed subtable and move mutations', function (): void {
    $record = orderWorkspaceWritesUser('tenant-state-moves', 'waiter-state-moves', ['orders.take']);

    app()->setLocale('en');
    orderWorkspaceWritesActingIn($record, 0, 'workspace-state-moves');
    $order = orderWorkspaceWritesOrder($record, 0);
    $subtable = orderWorkspaceWritesSubtable($order, 'Guest A');
    $category = null;
    $menuItem = null;

    for ($index = 1; $index <= 7; $index++) {
        $currentCategory = orderWorkspaceWritesCategory("Move State Menu {$index}", "Move State Category {$index}")['category'];

        for ($itemIndex = 1; $itemIndex <= ($index === 1 ? 13 : 1); $itemIndex++) {
            $currentItem = orderWorkspaceWritesItem(
                $currentCategory,
                $record['branches'][0],
                sprintf('Move State Dish %02d-%02d', $index, $itemIndex),
                priceMinor: 1000,
                sortOrder: $itemIndex,
            );

            if ($index === 1 && $itemIndex === 13) {
                $category = $currentCategory;
                $menuItem = $currentItem;
            }
        }
    }

    expect($category)->toBeInstanceOf(MenuCategory::class)
        ->and($menuItem)->toBeInstanceOf(MenuItem::class);

    $line = app(AddItem::class)((int) $order->id, (int) $menuItem->id, 1);

    $component = Livewire::actingAs($record['user'])
        ->test(OrderWorkspaceComponent::class, ['orderId' => (int) $order->id])
        ->set('menuSearch', 'Move State Dish')
        ->call('selectMenuCategory', (int) $category->id)
        ->call('nextMenuPage')
        ->call('nextMenuCategoryPage');

    $expectedState = [
        'menuSearch' => 'Move State Dish',
        'menuCategoryId' => (int) $category->id,
        'menuPage' => 2,
        'menuCategoryPage' => 2,
    ];

    $component
        ->set('newSubtableName', 'Guest B')
        ->call('createSubtable')
        ->assertSet('statusMessage', __('orders.flash.subtable_added'));
    orderWorkspaceWritesAssertMenuState($component, $expectedState);

    $component
        ->set('newSubtableName', 'guest b')
        ->call('createSubtable')
        ->assertSet('errorMessage', __('orders.workspace.validation.subtable_name_duplicate'));
    orderWorkspaceWritesAssertMenuState($component, $expectedState);

    $component
        ->set("moveTargetSubtableIds.{$line->id}", (string) $subtable->id)
        ->call('moveLineToSelectedSubtable', (int) $line->id)
        ->assertSet('statusMessage', __('orders.flash.item_moved'));
    orderWorkspaceWritesAssertMenuState($component, $expectedState);

    $component
        ->set("moveTargetSubtableIds.{$line->id}", (string) $subtable->id)
        ->call('moveLineToSelectedSubtable', (int) $line->id)
        ->assertSet('errorMessage', __('orders.item_move_noop'));
    orderWorkspaceWritesAssertMenuState($component, $expectedState);
});

it('keeps new workspace Livewire expressions rendered encoded and only scoped lifecycle affordances present', function (): void {
    $record = orderWorkspaceWritesUser('tenant-boundary-moves', 'manager-boundary-moves', ['orders.take', 'orders.cancel']);

    app()->setLocale('en');
    orderWorkspaceWritesActingIn($record, 0, 'workspace-boundary-moves');
    $order = orderWorkspaceWritesOrder($record, 0);
    $subtable = orderWorkspaceWritesSubtable($order, 'Boundary Subtable');
    $category = orderWorkspaceWritesCategory('Boundary Menu', 'Boundary Category')['category'];
    $menuItem = orderWorkspaceWritesItem($category, $record['branches'][0], 'Boundary Dish', priceMinor: 1000);
    $line = app(AddItem::class)((int) $order->id, (int) $menuItem->id, 1);

    $component = Livewire::actingAs($record['user'])
        ->test(OrderWorkspaceComponent::class, ['orderId' => (int) $order->id])
        ->assertSee('wire:click="createSubtable"', false)
        ->assertSee('Boundary Subtable', false)
        ->assertDontSee('closeSubtable', false)
        ->assertDontSee('renameSubtable', false)
        ->assertDontSee('targetOrderId', false)
        ->assertDontSee('moveOrder', false)
        ->assertSee('assignWaiter', false)
        ->assertSee('clearWaiter', false)
        ->assertSee('cancelOrder()', false)
        ->assertDontSee('applyDiscount', false)
        ->assertDontSee('discountOrder', false)
        ->assertDontSee('discountItem', false)
        ->assertDontSee('payment', false)
        ->assertDontSee('closeOrder', false)
        ->assertDontSee('<form', false)
        ->assertDontSee('wire:submit', false);

    $html = $component->html();

    assertRenderedHtmlHasNoUncompiledBladeDirectiveAttributes($html);
    assertRenderedLivewireBindingsResolve($html, OrderWorkspaceComponent::class);

    expect($html)->toContain('wire:click="moveLineToSelectedSubtable('.((int) $line->id).')"')
        ->and($html)->toContain('value="'.((int) $subtable->id).'"')
        ->and($html)->not->toContain('moveLineToSelectedSubtable(@js(')
        ->and($html)->not->toContain('{{');
});

it('keeps workspace render and new mutation query counts stable as lines and subtables grow', function (): void {
    $small = orderWorkspaceWritesSubtableMoveQueryCounts(lineCount: 1, subtableCount: 1, label: 'small');
    $largeLines = orderWorkspaceWritesSubtableMoveQueryCounts(lineCount: 10, subtableCount: 1, label: 'large-lines');
    $largeSubtables = orderWorkspaceWritesSubtableMoveQueryCounts(lineCount: 1, subtableCount: 10, label: 'large-subtables');

    expect($largeLines)->toBe($small)
        ->and($largeSubtables)->toBe($small)
        ->and($small)->toBe([
            'render' => 10,
            'create_subtable' => 33,
            'move_item' => 39,
        ]);
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

function orderWorkspaceWritesMoveSelectHtml(string $html, int $orderItemId): string
{
    preg_match(
        '/<select[^>]+id="order-workspace-move-target-'.preg_quote((string) $orderItemId, '/').'"[^>]*>.*?<\/select>/s',
        $html,
        $matches,
    );

    expect($matches[0] ?? null)->toBeString();

    return $matches[0];
}

/**
 * @param  array{menuSearch: string, menuCategoryId: int, menuPage: int, menuCategoryPage: int}  $expectedState
 */
function orderWorkspaceWritesAssertMenuState(mixed $component, array $expectedState): void
{
    foreach ($expectedState as $property => $value) {
        $component->assertSet($property, $value);
    }
}

/**
 * @return array{render: int, create_subtable: int, move_item: int}
 */
function orderWorkspaceWritesSubtableMoveQueryCounts(int $lineCount, int $subtableCount, string $label): array
{
    $record = orderWorkspaceWritesUser("tenant-query-moves-{$label}", "waiter-query-moves-{$label}", ['orders.take']);

    app()->setLocale('en');
    orderWorkspaceWritesActingIn($record, 0, "workspace-query-moves-{$label}");
    $order = orderWorkspaceWritesOrder($record, 0);
    $category = orderWorkspaceWritesCategory("Query Move Menu {$label}", "Query Move Category {$label}")['category'];
    $targetSubtable = null;

    for ($index = 1; $index <= $subtableCount; $index++) {
        $subtable = orderWorkspaceWritesSubtable($order, "Query Guest {$label} {$index}");

        if ($targetSubtable === null) {
            $targetSubtable = $subtable;
        }
    }

    $movedLine = null;

    for ($index = 1; $index <= $lineCount; $index++) {
        $menuItem = orderWorkspaceWritesItem(
            $category,
            $record['branches'][0],
            "Query Move Dish {$label} {$index}",
            priceMinor: 1000,
            sortOrder: $index,
        );
        $line = app(AddItem::class)((int) $order->id, (int) $menuItem->id, 1);

        if ($movedLine === null) {
            $movedLine = $line;
        }
    }

    expect($targetSubtable)->toBeInstanceOf(OrderSubtable::class)
        ->and($movedLine)->toBeInstanceOf(OrderItem::class);

    [, $renderQueries] = orderWorkspaceWritesQueryCount(
        fn () => Livewire::actingAs($record['user'])->test(OrderWorkspaceComponent::class, ['orderId' => (int) $order->id]),
    );

    [, $createSubtableQueries] = orderWorkspaceWritesQueryCount(
        fn () => Livewire::actingAs($record['user'])
            ->test(OrderWorkspaceComponent::class, ['orderId' => (int) $order->id])
            ->set('newSubtableName', "Query New Guest {$label}")
            ->call('createSubtable'),
    );

    [, $moveQueries] = orderWorkspaceWritesQueryCount(
        fn () => Livewire::actingAs($record['user'])
            ->test(OrderWorkspaceComponent::class, ['orderId' => (int) $order->id])
            ->set("moveTargetSubtableIds.{$movedLine->id}", (string) $targetSubtable->id)
            ->call('moveLineToSelectedSubtable', (int) $movedLine->id),
    );

    return [
        'render' => $renderQueries,
        'create_subtable' => $createSubtableQueries,
        'move_item' => $moveQueries,
    ];
}
