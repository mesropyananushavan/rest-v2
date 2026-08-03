<?php

declare(strict_types=1);

use App\Livewire\Admin\OrderBoard;
use App\Livewire\Admin\OrderWorkspace as OrderWorkspaceComponent;
use App\Modules\Identity\Infrastructure\Models\Permission;
use App\Modules\Identity\Infrastructure\Models\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Identity\Infrastructure\Models\UserBranchAssignment;
use App\Modules\Menu\Infrastructure\Models\MenuCategory;
use App\Modules\Menu\Infrastructure\Models\MenuItem;
use App\Modules\Orders\Application\FindOrderWorkspace;
use App\Modules\Orders\Application\OrderWorkspace;
use App\Modules\Orders\Contracts\OrderPermissions;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Orders\Infrastructure\Models\OrderItem;
use App\Modules\Orders\Infrastructure\Models\OrderSubtable;
use App\Modules\Tables\Infrastructure\Models\Hall;
use App\Modules\Tables\Infrastructure\Models\Table;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Modules\Tenancy\Infrastructure\Models\Branch;
use App\Modules\Tenancy\Infrastructure\Models\Tenant;
use App\Support\Audit\AuditLog;
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

it('requires authentication and the orders take permission for the workspace route and Livewire mount', function (): void {
    $allowed = orderWorkspaceUser('tenant-a', 'waiter-a', ['orders.take']);
    $denied = orderWorkspaceUser('tenant-b', 'viewer-b', []);

    orderWorkspaceActingIn($allowed, 0, 'orders-workspace-auth-setup');
    $table = orderWorkspaceTable($allowed, 0, 'Table 1');
    $order = orderWorkspaceDineInOrder($table, $allowed['user']);

    auth()->logout();
    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();

    $this->get(route('admin.orders.workspace', ['order' => (int) $order->id]))
        ->assertRedirect(route('login'));

    $this->actingAs($denied['user'])
        ->withSession(['branch_id' => (int) $denied['branches'][0]->id])
        ->get(route('admin.orders.workspace', ['order' => (int) $order->id]))
        ->assertForbidden();

    $this->actingAs($allowed['user'])
        ->withSession(['branch_id' => (int) $allowed['branches'][0]->id])
        ->get(route('admin.orders.workspace', ['order' => (int) $order->id]))
        ->assertOk()
        ->assertSeeLivewire(OrderWorkspaceComponent::class)
        ->assertSee(__('orders.workspace.heading', ['id' => (int) $order->id]), false);

    orderWorkspaceActingIn($denied, 0, 'orders-workspace-livewire-denied');

    Livewire::actingAs($denied['user'])
        ->test(OrderWorkspaceComponent::class, ['orderId' => (int) $order->id])
        ->assertStatus(403);
});

it('returns not found for orders outside the active open dine-in table workspace scope', function (): void {
    $tenantA = orderWorkspaceUser('tenant-a', 'waiter-a', ['orders.take'], branchCount: 2);
    $tenantB = orderWorkspaceUser('tenant-b', 'waiter-b', ['orders.take']);

    orderWorkspaceActingIn($tenantA, 0, 'orders-workspace-scope-a');
    $visible = orderWorkspaceDineInOrder(orderWorkspaceTable($tenantA, 0, 'Visible Table'), $tenantA['user']);
    $closed = orderWorkspaceDineInOrder(orderWorkspaceTable($tenantA, 0, 'Closed Table'), $tenantA['user'], status: 'closed');
    $cancelled = orderWorkspaceDineInOrder(orderWorkspaceTable($tenantA, 0, 'Cancelled Table'), $tenantA['user'], status: 'cancelled');
    $fastFood = orderWorkspaceTablelessOrder($tenantA['branches'][0], $tenantA['user'], 'fast_food');
    $dineInWithoutTable = orderWorkspaceTablelessOrder($tenantA['branches'][0], $tenantA['user'], 'dine_in');

    orderWorkspaceActingIn($tenantA, 1, 'orders-workspace-scope-other-branch');
    $otherBranch = orderWorkspaceDineInOrder(orderWorkspaceTable($tenantA, 1, 'Other Branch Table'), $tenantA['user']);

    orderWorkspaceActingIn($tenantB, 0, 'orders-workspace-scope-other-tenant');
    $otherTenant = orderWorkspaceDineInOrder(orderWorkspaceTable($tenantB, 0, 'Other Tenant Table'), $tenantB['user']);

    foreach ([$tenantA, $tenantB] as $record) {
        auth()->logout();
        app(BranchContext::class)->clear();
        app(TenantResolver::class)->clear();
    }

    $this->actingAs($tenantA['user'])
        ->withSession(['branch_id' => (int) $tenantA['branches'][0]->id])
        ->get(route('admin.orders.workspace', ['order' => (int) $visible->id]))
        ->assertOk();

    foreach ([
        (int) $closed->id,
        (int) $cancelled->id,
        (int) $fastFood->id,
        (int) $dineInWithoutTable->id,
        (int) $otherBranch->id,
        (int) $otherTenant->id,
        999_999,
    ] as $orderId) {
        $this->actingAs($tenantA['user'])
            ->withSession(['branch_id' => (int) $tenantA['branches'][0]->id])
            ->get(route('admin.orders.workspace', ['order' => $orderId]))
            ->assertNotFound();
    }
});

it('links occupied board tiles to the workspace while preserving free table modal behavior', function (): void {
    $record = orderWorkspaceUser('tenant-a', 'waiter-a', ['orders.take']);

    orderWorkspaceActingIn($record, 0, 'orders-workspace-board-links');
    $hall = orderWorkspaceHall($record['branches'][0], 'Main Hall');
    $occupiedTable = orderWorkspaceTableInHall($hall, 'Occupied Table');
    $freeTable = orderWorkspaceTableInHall($hall, 'Free Table');
    $order = orderWorkspaceDineInOrder($occupiedTable, $record['user'], clientCount: 2, totalMinor: 1200);
    $workspaceUrl = route('admin.orders.workspace', ['order' => (int) $order->id]);

    Livewire::actingAs($record['user'])
        ->test(OrderBoard::class)
        ->assertSee($workspaceUrl, false)
        ->assertSee('selectTable('.((int) $freeTable->id).')', false)
        ->assertDontSee('selectTable('.((int) $occupiedTable->id).')', false)
        ->call('selectTable', (int) $freeTable->id)
        ->assertSet('openModalVisible', true);
});

it('hides cancel controls from users that can only take orders', function (): void {
    $record = orderWorkspaceUser('tenant-a', 'waiter-a', [OrderPermissions::TAKE]);

    app()->setLocale('en');
    orderWorkspaceActingIn($record, 0, 'orders-workspace-cancel-hidden');
    $order = orderWorkspaceDineInOrder(orderWorkspaceTable($record, 0, 'Hidden Cancel Table'), $record['user']);
    orderWorkspaceItem($order, ['hy' => 'Ապուր', 'ru' => 'Суп', 'en' => 'Soup'], 1, 1000, 0, 1000);

    $component = Livewire::actingAs($record['user'])
        ->test(OrderWorkspaceComponent::class, ['orderId' => (int) $order->id])
        ->assertSee(__('orders.workspace.heading', ['id' => (int) $order->id]), false)
        ->assertDontSee(__('orders.workspace.actions.cancel_order'), false)
        ->assertDontSee(__('orders.workspace.confirm.cancel_order_message_with_lines', ['count' => 1]), false)
        ->assertDontSee('cancelOrder', false);

    assertRenderedLivewireBindingsResolve($component->html(), OrderWorkspaceComponent::class);
});

it('forbids direct cancel calls from users that can only take orders', function (): void {
    $record = orderWorkspaceUser('tenant-a', 'waiter-a', [OrderPermissions::TAKE]);

    orderWorkspaceActingIn($record, 0, 'orders-workspace-cancel-direct-denied');
    $order = orderWorkspaceDineInOrder(orderWorkspaceTable($record, 0, 'Direct Denied Cancel Table'), $record['user']);

    Livewire::actingAs($record['user'])
        ->test(OrderWorkspaceComponent::class, ['orderId' => (int) $order->id])
        ->call('cancelOrder')
        ->assertStatus(403);

    expect((string) $order->refresh()->status)->toBe('open');
});

it('cancels an open workspace order redirects to the board with flash and frees the table', function (): void {
    $record = orderWorkspaceUser('tenant-a', 'manager-a', [OrderPermissions::TAKE, OrderPermissions::CANCEL]);

    app()->setLocale('en');
    orderWorkspaceActingIn($record, 0, 'orders-workspace-cancel-success');
    $hall = orderWorkspaceHall($record['branches'][0], 'Main Hall');
    $table = orderWorkspaceTableInHall($hall, 'Cancel Table');
    $order = orderWorkspaceDineInOrder($table, $record['user'], totalMinor: 3000);
    orderWorkspaceItem($order, ['hy' => 'Ապուր', 'ru' => 'Суп', 'en' => 'Soup'], 1, 1000, 0, 1000);
    orderWorkspaceItem($order, ['hy' => 'Հաց', 'ru' => 'Хлеб', 'en' => 'Bread'], 2, 1000, 0, 2000);

    $component = Livewire::actingAs($record['user'])
        ->test(OrderWorkspaceComponent::class, ['orderId' => (int) $order->id])
        ->assertSee(__('orders.workspace.actions.cancel_order'), false)
        ->assertSee(__('orders.workspace.confirm.cancel_order_message_with_lines', ['count' => 2]), false)
        ->assertDontSee('<form', false)
        ->assertDontSee('wire:submit', false);

    assertRenderedHtmlHasNoUncompiledBladeDirectiveAttributes($component->html());
    assertRenderedLivewireBindingsResolve($component->html(), OrderWorkspaceComponent::class);

    $component
        ->call('cancelOrder')
        ->assertRedirect(route('admin.orders.board'));

    expect(session('status'))->toBe(__('orders.flash.cancelled'));

    $freshOrder = Order::query()->findOrFail((int) $order->id);

    expect((string) $freshOrder->status)->toBe('cancelled')
        ->and($freshOrder->closed_at)->not->toBeNull();

    $board = $this->actingAs($record['user'])
        ->withSession(['branch_id' => (int) $record['branches'][0]->id])
        ->get(route('admin.orders.board'))
        ->assertOk()
        ->assertSee(__('orders.flash.cancelled'), false)
        ->assertSee(__('orders.board.free'), false)
        ->assertSee('selectTable('.((int) $table->id).')', false)
        ->assertDontSee(route('admin.orders.workspace', ['order' => (int) $order->id]), false);

    assertRenderedHtmlHasNoUncompiledBladeDirectiveAttributes($board->getContent());
    assertRenderedLivewireBindingsResolve($board->getContent(), OrderBoard::class);
});

it('renders cancel confirmation consequence and line count in every locale', function (string $locale): void {
    $record = orderWorkspaceUser("tenant-{$locale}", "manager-{$locale}", [OrderPermissions::TAKE, OrderPermissions::CANCEL]);

    app()->setLocale($locale);
    orderWorkspaceActingIn($record, 0, "orders-workspace-cancel-copy-{$locale}");
    $emptyOrder = orderWorkspaceDineInOrder(orderWorkspaceTable($record, 0, "Empty {$locale}"), $record['user']);
    $loadedOrder = orderWorkspaceDineInOrder(orderWorkspaceTable($record, 0, "Loaded {$locale}"), $record['user']);
    orderWorkspaceItem($loadedOrder, ['hy' => 'Ապրանք 1', 'ru' => 'Позиция 1', 'en' => 'Item 1'], 1, 1000, 0, 1000);
    orderWorkspaceItem($loadedOrder, ['hy' => 'Ապրանք 2', 'ru' => 'Позиция 2', 'en' => 'Item 2'], 1, 1000, 0, 1000);

    Livewire::actingAs($record['user'])
        ->test(OrderWorkspaceComponent::class, ['orderId' => (int) $emptyOrder->id])
        ->assertSee(__('orders.workspace.confirm.cancel_order_message_empty'), false)
        ->assertDontSee(__('orders.workspace.confirm.cancel_order_message_with_lines', ['count' => 2]), false);

    Livewire::actingAs($record['user'])
        ->test(OrderWorkspaceComponent::class, ['orderId' => (int) $loadedOrder->id])
        ->assertSee(__('orders.workspace.confirm.cancel_order_message_with_lines', ['count' => 2]), false);
})->with(['hy', 'ru', 'en']);

it('returns translated errors for stale and out of scope cancel attempts without changing data', function (): void {
    $tenantA = orderWorkspaceUser('tenant-a', 'manager-a', [OrderPermissions::TAKE, OrderPermissions::CANCEL], branchCount: 2);
    $tenantB = orderWorkspaceUser('tenant-b', 'manager-b', [OrderPermissions::TAKE, OrderPermissions::CANCEL]);

    app()->setLocale('en');
    orderWorkspaceActingIn($tenantA, 0, 'orders-workspace-cancel-scope-a');
    $order = orderWorkspaceDineInOrder(orderWorkspaceTable($tenantA, 0, 'Visible Cancel Table'), $tenantA['user']);
    $safeMountOrder = orderWorkspaceDineInOrder(orderWorkspaceTable($tenantA, 0, 'Safe Mount Cancel Table'), $tenantA['user']);

    orderWorkspaceActingIn($tenantA, 1, 'orders-workspace-cancel-scope-other-branch');
    $otherBranchOrder = orderWorkspaceDineInOrder(orderWorkspaceTable($tenantA, 1, 'Other Branch Cancel Table'), $tenantA['user']);

    orderWorkspaceActingIn($tenantB, 0, 'orders-workspace-cancel-scope-b');
    $otherTenantOrder = orderWorkspaceDineInOrder(orderWorkspaceTable($tenantB, 0, 'Other Tenant Cancel Table'), $tenantB['user']);

    orderWorkspaceActingIn($tenantA, 0, 'orders-workspace-cancel-scope-mounted');

    $component = Livewire::actingAs($tenantA['user'])
        ->test(OrderWorkspaceComponent::class, ['orderId' => (int) $order->id]);

    $order->forceFill([
        'status' => 'cancelled',
        'closed_at' => now(),
    ])->save();

    $component
        ->call('cancelOrder')
        ->assertSet('errorMessage', __('orders.order_not_open'))
        ->assertSee(__('orders.workspace.unavailable_title'), false)
        ->assertDontSee('cancelOrder', false);

    $freshOrder = Order::query()->findOrFail((int) $order->id);

    expect((string) $freshOrder->status)->toBe('cancelled');

    foreach ([$otherBranchOrder, $otherTenantOrder] as $outOfScopeOrder) {
        Livewire::actingAs($tenantA['user'])
            ->test(OrderWorkspaceComponent::class, ['orderId' => (int) $safeMountOrder->id])
            ->set('orderId', (int) $outOfScopeOrder->id)
            ->call('cancelOrder')
            ->assertSet('errorMessage', __('orders.workspace.errors.generic'));

        expect((string) $outOfScopeOrder->refresh()->status)->toBe('open');
    }
});

it('keeps mounted cancel safe after the order is closed or cancelled elsewhere', function (string $status): void {
    $record = orderWorkspaceUser('tenant-a', 'manager-a', [OrderPermissions::TAKE, OrderPermissions::CANCEL]);

    app()->setLocale('en');
    orderWorkspaceActingIn($record, 0, "orders-workspace-cancel-stale-{$status}");
    $order = orderWorkspaceDineInOrder(orderWorkspaceTable($record, 0, "Stale Cancel {$status}"), $record['user']);
    orderWorkspaceItem($order, ['hy' => 'Ապրանք', 'ru' => 'Позиция', 'en' => 'Item'], 1, 1000, 0, 1000);

    $component = Livewire::actingAs($record['user'])
        ->test(OrderWorkspaceComponent::class, ['orderId' => (int) $order->id]);

    $order->forceFill([
        'status' => $status,
        'closed_at' => now(),
    ])->save();

    $component
        ->call('cancelOrder')
        ->assertSet('errorMessage', __('orders.order_not_open'))
        ->assertSee(__('orders.workspace.unavailable_title'), false)
        ->assertDontSee('cancelOrder', false);

    $freshOrder = Order::query()->findOrFail((int) $order->id);

    expect((string) $freshOrder->status)->toBe($status)
        ->and(OrderItem::query()->where('order_id', (int) $order->id)->count())->toBe(1);
})->with(['closed', 'cancelled']);

it('maps the branch context cancel domain error to a translated message', function (): void {
    $record = orderWorkspaceUser('tenant-a', 'manager-a', [OrderPermissions::TAKE, OrderPermissions::CANCEL]);

    app()->setLocale('en');
    orderWorkspaceActingIn($record, 0, 'orders-workspace-cancel-branch-context');
    $order = orderWorkspaceDineInOrder(orderWorkspaceTable($record, 0, 'Branch Context Cancel Table'), $record['user']);

    $component = Livewire::actingAs($record['user'])
        ->test(OrderWorkspaceComponent::class, ['orderId' => (int) $order->id]);

    app(BranchContext::class)->clear();

    $component
        ->call('cancelOrder')
        ->assertSet('errorMessage', __('orders.branch_context_required'))
        ->assertSee(__('orders.workspace.unavailable_title'), false);

    expect((string) $order->refresh()->status)->toBe('open');
});

it('assigns and clears branch scoped waiters through the workspace', function (): void {
    $tenantA = orderWorkspaceUser('tenant-a', 'waiter-a', ['orders.take'], branchCount: 2);
    $tenantB = orderWorkspaceUser('tenant-b', 'waiter-b', ['orders.take']);

    $assignable = orderWorkspaceStaffUser($tenantA, 0, 'Assignable Waiter', ['orders.take']);
    $otherBranch = orderWorkspaceStaffUser($tenantA, 1, 'Other Branch Waiter', ['orders.take']);
    $noPermission = orderWorkspaceStaffUser($tenantA, 0, 'Viewer Only', []);
    $foreign = orderWorkspaceStaffUser($tenantB, 0, 'Foreign Waiter', ['orders.take']);
    $table = orderWorkspaceTable($tenantA, 0, 'Assignable Table');

    app()->setLocale('en');
    orderWorkspaceActingIn($tenantA, 0, 'orders-workspace-waiter-assignment');
    $order = orderWorkspaceDineInOrder($table, null);

    $component = Livewire::actingAs($tenantA['user'])
        ->test(OrderWorkspaceComponent::class, ['orderId' => (int) $order->id])
        ->assertSee(__('orders.workspace.waiter.label'), false)
        ->assertSee(__('orders.workspace.waiter.not_assigned'), false)
        ->assertSee('Assignable Waiter', false)
        ->assertDontSee('Other Branch Waiter', false)
        ->assertDontSee('Viewer Only', false)
        ->assertDontSee('Foreign Waiter', false);

    assertRenderedHtmlHasNoUncompiledBladeDirectiveAttributes($component->html());
    assertRenderedLivewireBindingsResolve($component->html(), OrderWorkspaceComponent::class);

    $component
        ->set('selectedWaiterId', (string) $assignable->id)
        ->call('assignWaiter')
        ->assertSet('statusMessage', __('orders.flash.waiter_assigned'))
        ->assertSee('Assignable Waiter', false);

    $freshOrder = Order::query()->findOrFail((int) $order->id);
    $assignedAudit = AuditLog::query()->where('action', 'orders.order.waiter_assigned')->latest('id')->firstOrFail();

    expect((int) $freshOrder->waiter_id)->toBe((int) $assignable->id)
        ->and($assignedAudit->tenant_id)->toBe((int) $tenantA['tenant']->id)
        ->and($assignedAudit->after_json['waiter_id'])->toBe((int) $assignable->id);

    $component
        ->call('clearWaiter')
        ->assertSet('statusMessage', __('orders.flash.waiter_cleared'))
        ->assertSee(__('orders.workspace.waiter.not_assigned'), false);

    expect(Order::query()->findOrFail((int) $order->id)->waiter_id)->toBeNull()
        ->and(AuditLog::query()->where('action', 'orders.order.waiter_assigned')->count())->toBe(2);

    $component
        ->set('selectedWaiterId', (string) $otherBranch->id)
        ->call('assignWaiter')
        ->assertSet('errorMessage', __('orders.waiter_not_assignable'));

    expect(Order::query()->findOrFail((int) $order->id)->waiter_id)->toBeNull()
        ->and(AuditLog::query()->where('action', 'orders.order.waiter_assigned')->count())->toBe(2);

    expect($noPermission)->toBeInstanceOf(User::class)
        ->and($foreign)->toBeInstanceOf(User::class);
});

it('forbids cancel mounting without the orders take permission', function (): void {
    $allowed = orderWorkspaceUser('tenant-a', 'waiter-a', ['orders.take']);
    $denied = orderWorkspaceUser('tenant-b', 'viewer-b', []);

    orderWorkspaceActingIn($allowed, 0, 'orders-workspace-cancel-permission');
    $order = orderWorkspaceDineInOrder(orderWorkspaceTable($allowed, 0, 'Permission Cancel Table'), $allowed['user']);

    orderWorkspaceActingIn($denied, 0, 'orders-workspace-cancel-permission-denied');

    Livewire::actingAs($denied['user'])
        ->test(OrderWorkspaceComponent::class, ['orderId' => (int) $order->id])
        ->assertStatus(403);

    expect((string) $order->refresh()->status)->toBe('open');
});

it('keeps cancel-visible workspace render query count stable as line count grows', function (): void {
    $small = orderWorkspaceCancelRenderQueryCount(1, 'small');
    $large = orderWorkspaceCancelRenderQueryCount(8, 'large');

    expect($small)->toBe(8)
        ->and($large)->toBe(8);
});

it('keeps order translation key sets identical and reuses the cancelled flash key', function (): void {
    app()->setLocale('hy');

    $translations = [
        'hy' => require base_path('lang/hy/orders.php'),
        'ru' => require base_path('lang/ru/orders.php'),
        'en' => require base_path('lang/en/orders.php'),
    ];

    $keys = [];

    foreach ($translations as $locale => $localeTranslations) {
        $keys[$locale] = orderWorkspaceFlattenTranslationKeys($localeTranslations);
    }

    expect($keys['hy'])->toBe($keys['ru'])
        ->and($keys['hy'])->toBe($keys['en'])
        ->and($translations['hy']['flash']['cancelled'])->toBe(__('orders.flash.cancelled'));
});

it('renders header subtables assigned and unassigned items and totals read only', function (): void {
    $record = orderWorkspaceUser('tenant-a', 'waiter-a', ['orders.take']);

    orderWorkspaceActingIn($record, 0, 'orders-workspace-render');
    $table = orderWorkspaceTable($record, 0, 'Table 7');
    $order = orderWorkspaceDineInOrder(
        $table,
        $record['user'],
        clientCount: 4,
        comment: "Window seat\nNo onions",
        subtotalMinor: 7350,
        discountMinor: 450,
        totalMinor: 6900,
    );
    $subtable = orderWorkspaceSubtable($order, 'Guest 1');
    orderWorkspaceItem($order, ['hy' => 'Տոլմա', 'ru' => 'Долма', 'en' => 'Dolma'], 2, 1250, 175, 2325, subtableId: (int) $subtable->id);
    orderWorkspaceItem($order, ['hy' => 'Թան', 'ru' => 'Тан', 'en' => 'Tan'], 1, 5025, 275, 4750);

    app()->setLocale('hy');

    Livewire::actingAs($record['user'])
        ->test(OrderWorkspaceComponent::class, ['orderId' => (int) $order->id])
        ->assertSee(__('orders.workspace.heading', ['id' => (int) $order->id]), false)
        ->assertSee(__('orders.workspace.table_label', ['id' => (int) $table->id]), false)
        ->assertSee(__('orders.types.dine_in'), false)
        ->assertSee(__('orders.status.open'), false)
        ->assertSee(__('orders.board.guests', ['count' => 4]), false)
        ->assertSee('Window seat', false)
        ->assertSee('No onions', false)
        ->assertSee('Guest 1', false)
        ->assertSee(__('orders.workspace.unassigned_items'), false)
        ->assertSee('Տոլմա', false)
        ->assertSee('Թան', false)
        ->assertSee(MoneyFormatter::format(new Money(1250, 'AMD'), app()->getLocale()), false)
        ->assertSee(MoneyFormatter::format(new Money(175, 'AMD'), app()->getLocale()), false)
        ->assertSee(MoneyFormatter::format(new Money(2325, 'AMD'), app()->getLocale()), false)
        ->assertSee(MoneyFormatter::format(new Money(7350, 'AMD'), app()->getLocale()), false)
        ->assertSee(MoneyFormatter::format(new Money(450, 'AMD'), app()->getLocale()), false)
        ->assertSee(MoneyFormatter::format(new Money(6900, 'AMD'), app()->getLocale()), false)
        ->assertDontSee('<form', false)
        ->assertDontSee('wire:submit', false)
        ->assertDontSee('openOrder', false)
        ->assertDontSee('AddItem', false)
        ->assertDontSee(__('orders.board.action_open'), false);
});

it('renders localized snapshots with English fallback and neutral fallback for null or malformed snapshots', function (): void {
    $record = orderWorkspaceUser('tenant-a', 'waiter-a', ['orders.take']);

    app()->setLocale('ru');
    orderWorkspaceActingIn($record, 0, 'orders-workspace-snapshots');
    $table = orderWorkspaceTable($record, 0, 'Snapshot Table');
    $order = orderWorkspaceDineInOrder($table, $record['user'], totalMinor: 4000);
    orderWorkspaceItem($order, ['hy' => 'Տոլմա', 'ru' => 'Долма', 'en' => 'Dolma'], 1, 1000, 0, 1000);
    orderWorkspaceItem($order, ['hy' => 'Only Hy', 'ru' => '', 'en' => 'English Fallback'], 1, 1000, 0, 1000);
    orderWorkspaceItem($order, null, 1, 1000, 0, 1000);
    orderWorkspaceMalformedItem($order, ['hy' => 'Only Armenian'], 1, 1000, 0, 1000);
    orderWorkspaceScalarSnapshotItem($order, 'Scalar Snapshot', 1, 1000, 0, 1000);

    Livewire::actingAs($record['user'])
        ->test(OrderWorkspaceComponent::class, ['orderId' => (int) $order->id])
        ->assertSee('Долма', false)
        ->assertSee(__('orders.workspace.item_name_missing'), false)
        ->assertDontSee('English Fallback', false)
        ->assertDontSee('Only Armenian', false)
        ->assertDontSee('Scalar Snapshot', false);

    app()->setLocale('ka');

    Livewire::actingAs($record['user'])
        ->test(OrderWorkspaceComponent::class, ['orderId' => (int) $order->id])
        ->assertSee('Dolma', false);
});

it('escapes user editable comments subtable names and item snapshots', function (): void {
    $record = orderWorkspaceUser('tenant-a', 'waiter-a', ['orders.take']);

    orderWorkspaceActingIn($record, 0, 'orders-workspace-escaping');
    $order = orderWorkspaceDineInOrder(
        orderWorkspaceTable($record, 0, 'Escaping Table'),
        $record['user'],
        comment: '<script>alert("comment")</script>',
    );
    orderWorkspaceSubtable($order, '<img src=x onerror=alert(1)>');
    orderWorkspaceItem($order, [
        'hy' => '<script>alert("item")</script>',
        'ru' => '<script>alert("item")</script>',
        'en' => '<script>alert("item")</script>',
    ], 1, 1000, 0, 1000);

    Livewire::actingAs($record['user'])
        ->test(OrderWorkspaceComponent::class, ['orderId' => (int) $order->id])
        ->assertDontSee('<script>alert("comment")</script>', false)
        ->assertDontSee('<img src=x onerror=alert(1)>', false)
        ->assertDontSee('<script>alert("item")</script>', false)
        ->assertSee(e('<script>alert("comment")</script>'), false)
        ->assertSee(e('<img src=x onerror=alert(1)>'), false)
        ->assertSee(e('<script>alert("item")</script>'), false);
});

it('renders stored historical names after menu rename archive and hard delete without menu reads', function (): void {
    $record = orderWorkspaceUser('tenant-a', 'waiter-a', ['orders.take']);

    orderWorkspaceActingIn($record, 0, 'orders-workspace-menu-deleted');
    $menuItem = orderWorkspaceMenuItem($record, 0, 'Dolma');
    $snapshot = ['hy' => 'Տոլմա', 'ru' => 'Долма', 'en' => 'Dolma'];
    $order = orderWorkspaceDineInOrder(orderWorkspaceTable($record, 0, 'Table 9'), $record['user'], totalMinor: 1000);
    orderWorkspaceItem($order, $snapshot, 1, 1000, 0, 1000, menuItemId: (int) $menuItem->id);

    $menuItem->update(['translated_name' => ['hy' => 'Նոր Տոլմա', 'ru' => 'Новая Долма', 'en' => 'New Dolma']]);
    $menuItem->delete();
    $menuItem->forceDelete();

    DB::flushQueryLog();
    DB::enableQueryLog();

    try {
        $workspace = app(FindOrderWorkspace::class)((int) $order->id);
        $queries = DB::getQueryLog();
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }

    expect($workspace)->toBeInstanceOf(OrderWorkspace::class)
        ->and($workspace->items[0]->nameSnapshot)->toBe($snapshot)
        ->and($queries)->toHaveCount(3)
        ->and(collect($queries)->contains(fn (array $query): bool => str_contains((string) $query['query'], 'menu_items')))->toBeFalse();

    app()->setLocale('hy');

    Livewire::actingAs($record['user'])
        ->test(OrderWorkspaceComponent::class, ['orderId' => (int) $order->id])
        ->assertSee('Տոլմա', false)
        ->assertDontSee('Նոր Տոլմա', false);
});

it('keeps workspace read query count fixed as subtables and items grow', function (): void {
    $record = orderWorkspaceUser('tenant-a', 'waiter-a', ['orders.take']);

    orderWorkspaceActingIn($record, 0, 'orders-workspace-query-count');
    $order = orderWorkspaceDineInOrder(orderWorkspaceTable($record, 0, 'Query Table'), $record['user'], totalMinor: 6000);

    for ($index = 1; $index <= 4; $index++) {
        $subtable = orderWorkspaceSubtable($order, "Guest {$index}");
        orderWorkspaceItem($order, ['hy' => "Ապրանք {$index}", 'ru' => "Блюдо {$index}", 'en' => "Dish {$index}"], 1, 1000, 0, 1000, subtableId: (int) $subtable->id);
    }

    for ($index = 5; $index <= 6; $index++) {
        orderWorkspaceItem($order, ['hy' => "Ապրանք {$index}", 'ru' => "Блюдо {$index}", 'en' => "Dish {$index}"], 1, 1000, 0, 1000);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();

    try {
        $workspace = app(FindOrderWorkspace::class)((int) $order->id);
        $queryCount = count(DB::getQueryLog());
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }

    expect($workspace->subtables)->toHaveCount(4)
        ->and($workspace->items)->toHaveCount(6)
        ->and($queryCount)->toBe(3);
});

/**
 * @param  list<string>  $permissionCodes
 * @return array{tenant: Tenant, branches: list<Branch>, user: User}
 */
function orderWorkspaceUser(string $tenantSlug, string $username, array $permissionCodes, int $branchCount = 1): array
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
 * @param  list<string>  $permissionCodes
 */
function orderWorkspaceStaffUser(array $record, int $branchIndex, string $name, array $permissionCodes, bool $active = true): User
{
    app(TenantResolver::class)->set((int) $record['tenant']->id);
    app(BranchContext::class)->set((int) $record['branches'][$branchIndex]->id);

    $username = str($name)->slug('-')->toString();
    $role = Role::query()->create([
        'code' => "{$username}-role",
        'name' => "{$name} Role",
    ]);

    $permissions = collect($permissionCodes)
        ->map(fn (string $code): Permission => Permission::query()->firstOrCreate(
            ['code' => $code],
            ['name' => $code],
        ));

    if ($permissions->isNotEmpty()) {
        $role->permissions()->attach(
            $permissions->pluck('id')->all(),
            ['tenant_id' => (int) $record['tenant']->id],
        );
    }

    $user = User::query()->create([
        'role_id' => (int) $role->id,
        'name' => $name,
        'email' => "{$username}@smartrest.test",
        'username' => $username,
        'default_locale' => 'en',
        'active' => $active,
        'is_superadmin' => false,
        'password' => Hash::make('password'),
    ]);

    UserBranchAssignment::query()->create([
        'user_id' => (int) $user->id,
        'branch_id' => (int) $record['branches'][$branchIndex]->id,
    ]);

    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();

    return $user;
}

/**
 * @param  array{tenant: Tenant, branches: list<Branch>, user: User}  $record
 */
function orderWorkspaceActingIn(array $record, int $branchIndex, string $requestId): void
{
    app(TenantResolver::class)->set((int) $record['tenant']->id);
    app(BranchContext::class)->set((int) $record['branches'][$branchIndex]->id);
    auth()->login($record['user']);
    LogContext::start($requestId, 'orders');
}

/**
 * @param  array{tenant: Tenant, branches: list<Branch>, user: User}  $record
 */
function orderWorkspaceTable(array $record, int $branchIndex, string $name): Table
{
    app(TenantResolver::class)->set((int) $record['tenant']->id);
    app(BranchContext::class)->set((int) $record['branches'][$branchIndex]->id);

    return orderWorkspaceTableInHall(orderWorkspaceHall($record['branches'][$branchIndex], "{$name} Hall"), $name);
}

function orderWorkspaceHall(Branch $branch, string $name): Hall
{
    return Hall::query()->create([
        'branch_id' => (int) $branch->id,
        'translated_name' => orderWorkspaceTranslations($name),
        'color' => '#5FA8D3',
        'sort_order' => 10,
        'active' => true,
    ]);
}

function orderWorkspaceTableInHall(Hall $hall, string $name): Table
{
    return Table::query()->create([
        'branch_id' => (int) $hall->branch_id,
        'hall_id' => (int) $hall->id,
        'translated_name' => orderWorkspaceTranslations($name),
        'type' => 'standard',
        'shape' => 'square',
        'hdm_department' => 1,
        'is_delivery' => false,
        'sort_order' => 10,
        'active' => true,
    ]);
}

function orderWorkspaceDineInOrder(
    Table $table,
    ?User $waiter,
    string $status = 'open',
    ?string $comment = null,
    int $clientCount = 1,
    int $subtotalMinor = 0,
    int $discountMinor = 0,
    int $totalMinor = 0,
): Order {
    return Order::query()->create([
        'branch_id' => (int) $table->branch_id,
        'type' => 'dine_in',
        'status' => $status,
        'table_id' => (int) $table->id,
        'waiter_id' => $waiter instanceof User ? (int) $waiter->id : null,
        'opened_at' => now()->subMinutes(25),
        'closed_at' => $status === 'open' ? null : now(),
        'client_count' => $clientCount,
        'comment' => $comment,
        'subtotal_minor' => $subtotalMinor,
        'discount_minor' => $discountMinor,
        'total_minor' => $totalMinor,
        'currency' => 'AMD',
    ]);
}

function orderWorkspaceTablelessOrder(Branch $branch, ?User $waiter, string $type): Order
{
    return Order::query()->create([
        'branch_id' => (int) $branch->id,
        'type' => $type,
        'status' => 'open',
        'table_id' => null,
        'waiter_id' => $waiter instanceof User ? (int) $waiter->id : null,
        'opened_at' => now(),
        'closed_at' => null,
        'client_count' => 1,
        'subtotal_minor' => 0,
        'discount_minor' => 0,
        'total_minor' => 0,
        'currency' => 'AMD',
    ]);
}

function orderWorkspaceSubtable(Order $order, string $name): OrderSubtable
{
    return OrderSubtable::query()->create([
        'branch_id' => (int) $order->branch_id,
        'order_id' => (int) $order->id,
        'name' => $name,
        'status' => 'open',
    ]);
}

/**
 * @param  array{hy: string, ru: string, en: string}|null  $snapshot
 */
function orderWorkspaceItem(
    Order $order,
    ?array $snapshot,
    int $qty,
    int $unitPriceMinor,
    int $discountMinor,
    int $totalMinor,
    ?int $subtableId = null,
    ?int $menuItemId = null,
): OrderItem {
    return OrderItem::query()->create([
        'branch_id' => (int) $order->branch_id,
        'order_id' => (int) $order->id,
        'subtable_id' => $subtableId,
        'menu_item_id' => $menuItemId ?? 10_000 + (int) $order->id + $qty,
        'menu_item_name_snapshot' => $snapshot,
        'qty' => $qty,
        'unit_price_minor' => $unitPriceMinor,
        'discount_minor' => $discountMinor,
        'total_minor' => $totalMinor,
        'currency' => 'AMD',
        'seller_id' => null,
        'preparation_status' => 'pending',
    ]);
}

/**
 * @param  array<string, mixed>  $snapshot
 */
function orderWorkspaceMalformedItem(
    Order $order,
    array $snapshot,
    int $qty,
    int $unitPriceMinor,
    int $discountMinor,
    int $totalMinor,
): void {
    DB::table('order_items')->insert([
        'tenant_id' => (int) $order->tenant_id,
        'branch_id' => (int) $order->branch_id,
        'order_id' => (int) $order->id,
        'subtable_id' => null,
        'menu_item_id' => 99_999,
        'menu_item_name_snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
        'qty' => $qty,
        'unit_price_minor' => $unitPriceMinor,
        'discount_minor' => $discountMinor,
        'total_minor' => $totalMinor,
        'currency' => 'AMD',
        'seller_id' => null,
        'preparation_status' => 'pending',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function orderWorkspaceScalarSnapshotItem(
    Order $order,
    string $snapshot,
    int $qty,
    int $unitPriceMinor,
    int $discountMinor,
    int $totalMinor,
): void {
    DB::table('order_items')->insert([
        'tenant_id' => (int) $order->tenant_id,
        'branch_id' => (int) $order->branch_id,
        'order_id' => (int) $order->id,
        'subtable_id' => null,
        'menu_item_id' => 99_998,
        'menu_item_name_snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
        'qty' => $qty,
        'unit_price_minor' => $unitPriceMinor,
        'discount_minor' => $discountMinor,
        'total_minor' => $totalMinor,
        'currency' => 'AMD',
        'seller_id' => null,
        'preparation_status' => 'pending',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/**
 * @param  array{tenant: Tenant, branches: list<Branch>, user: User}  $record
 */
function orderWorkspaceMenuItem(array $record, int $branchIndex, string $name): MenuItem
{
    app(TenantResolver::class)->set((int) $record['tenant']->id);
    app(BranchContext::class)->set((int) $record['branches'][$branchIndex]->id);

    $root = MenuCategory::query()->create([
        'translated_name' => orderWorkspaceTranslations("{$name} Root"),
        'sort_order' => 0,
        'active' => true,
    ]);

    $category = MenuCategory::query()->create([
        'parent_id' => (int) $root->id,
        'translated_name' => orderWorkspaceTranslations("{$name} Category"),
        'sort_order' => 10,
        'active' => true,
    ]);

    return MenuItem::query()->create([
        'branch_id' => (int) $record['branches'][$branchIndex]->id,
        'category_id' => (int) $category->id,
        'translated_name' => orderWorkspaceTranslations($name),
        'translated_description' => orderWorkspaceTranslations("{$name} Description"),
        'price_minor' => 1000,
        'currency' => 'AMD',
        'active' => true,
    ]);
}

/**
 * @return array{hy: string, ru: string, en: string}
 */
function orderWorkspaceTranslations(string $text): array
{
    return [
        'hy' => $text,
        'ru' => $text,
        'en' => $text,
    ];
}

function orderWorkspaceCancelRenderQueryCount(int $lineCount, string $label): int
{
    $record = orderWorkspaceUser("tenant-cancel-query-{$label}", "manager-cancel-query-{$label}", [OrderPermissions::TAKE, OrderPermissions::CANCEL]);

    orderWorkspaceActingIn($record, 0, "orders-workspace-cancel-query-{$label}");
    $order = orderWorkspaceDineInOrder(orderWorkspaceTable($record, 0, "Cancel Query {$label}"), $record['user']);

    for ($index = 1; $index <= $lineCount; $index++) {
        orderWorkspaceItem($order, ['hy' => "Ապրանք {$index}", 'ru' => "Позиция {$index}", 'en' => "Item {$index}"], 1, 1000, 0, 1000);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();

    try {
        $component = Livewire::actingAs($record['user'])
            ->test(OrderWorkspaceComponent::class, ['orderId' => (int) $order->id]);
        $queryCount = count(DB::getQueryLog());
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }

    $component
        ->assertSee(__('orders.workspace.actions.cancel_order'), false)
        ->assertSee(__('orders.workspace.confirm.cancel_order_message_with_lines', ['count' => $lineCount]), false);

    assertRenderedLivewireBindingsResolve($component->html(), OrderWorkspaceComponent::class);

    return $queryCount;
}

/**
 * @param  array<string, mixed>  $translations
 * @return list<string>
 */
function orderWorkspaceFlattenTranslationKeys(array $translations, string $prefix = ''): array
{
    $keys = [];

    foreach ($translations as $key => $value) {
        $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

        if (is_array($value)) {
            array_push($keys, ...orderWorkspaceFlattenTranslationKeys($value, $path));

            continue;
        }

        $keys[] = $path;
    }

    sort($keys);

    return $keys;
}
