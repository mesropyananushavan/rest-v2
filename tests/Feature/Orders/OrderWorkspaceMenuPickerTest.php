<?php

declare(strict_types=1);

use App\Livewire\Admin\OrderWorkspace as OrderWorkspaceComponent;
use App\Modules\Identity\Infrastructure\Models\Permission;
use App\Modules\Identity\Infrastructure\Models\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Identity\Infrastructure\Models\UserBranchAssignment;
use App\Modules\Menu\Application\ArchiveMenuItem;
use App\Modules\Menu\Application\CreateMenuCategory;
use App\Modules\Menu\Application\CreateMenuItem;
use App\Modules\Menu\Contracts\MenuCatalog;
use App\Modules\Menu\Infrastructure\Models\MenuCategory;
use App\Modules\Menu\Infrastructure\Models\MenuItem;
use App\Modules\Orders\Application\FindOrderWorkspace;
use App\Modules\Orders\Infrastructure\Models\Order;
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

it('renders the read only menu picker for an orders take user without menu item management permission', function (): void {
    $record = orderWorkspacePickerUser('tenant-a', 'waiter-a', ['orders.take']);

    app()->setLocale('en');
    orderWorkspacePickerActingIn($record, 0, 'workspace-picker-authorized');
    $order = orderWorkspacePickerOrder($record, 0);
    $category = orderWorkspacePickerCategory('Dining Menu', 'Hot Dishes')['category'];
    $item = orderWorkspacePickerItem($category, $record['branches'][0], 'Sellable Lavash', priceMinor: 175000);

    Livewire::actingAs($record['user'])
        ->test(OrderWorkspaceComponent::class, ['orderId' => (int) $order->id])
        ->assertSee(__('orders.workspace.menu_picker.title'), false)
        ->assertSee('Sellable Lavash', false)
        ->assertSee(MoneyFormatter::format(new Money(175000, 'AMD'), 'en'), false)
        ->assertDontSee('menu.items.manage', false)
        ->assertDontSee('<form', false)
        ->assertDontSee('type="number"', false)
        ->assertDontSee('wire:submit', false)
        ->assertDontSee('openOrder', false)
        ->assertDontSee('AddItem', false)
        ->assertDontSee(__('orders.board.action_open'), false);

    expect($record['user']->can('orders.take'))->toBeTrue()
        ->and($record['user']->can('menu.items.manage'))->toBeFalse()
        ->and($item->translatedName()->forLocale('en'))->toBe('Sellable Lavash');
});

it('shows only sellable menu items for the active tenant and branch inside the workspace', function (): void {
    $tenantA = orderWorkspacePickerUser('tenant-a', 'waiter-a', ['orders.take'], branchCount: 2);
    $tenantB = orderWorkspacePickerUser('tenant-b', 'waiter-b', ['orders.take']);

    orderWorkspacePickerActingIn($tenantA, 0, 'workspace-picker-scope-a');
    $order = orderWorkspacePickerOrder($tenantA, 0);
    $category = orderWorkspacePickerCategory('Tenant A Menu', 'Tenant A Category')['category'];
    $visible = orderWorkspacePickerItem($category, $tenantA['branches'][0], 'Visible Branch Dish');
    $inactive = orderWorkspacePickerItem($category, $tenantA['branches'][0], 'Inactive Branch Dish', active: false);
    $archived = orderWorkspacePickerItem($category, $tenantA['branches'][0], 'Archived Branch Dish');
    app(ArchiveMenuItem::class)((int) $archived->id);
    $otherBranch = orderWorkspacePickerItem($category, $tenantA['branches'][1], 'Other Branch Dish');

    orderWorkspacePickerActingIn($tenantB, 0, 'workspace-picker-scope-b');
    $foreignCategory = orderWorkspacePickerCategory('Tenant B Menu', 'Tenant B Category')['category'];
    $foreign = orderWorkspacePickerItem($foreignCategory, $tenantB['branches'][0], 'Foreign Tenant Dish');

    orderWorkspacePickerActingIn($tenantA, 0, 'workspace-picker-scope-render');

    Livewire::actingAs($tenantA['user'])
        ->test(OrderWorkspaceComponent::class, ['orderId' => (int) $order->id])
        ->assertSee('Visible Branch Dish', false)
        ->assertDontSee('Inactive Branch Dish', false)
        ->assertDontSee('Archived Branch Dish', false)
        ->assertDontSee('Other Branch Dish', false)
        ->assertDontSee('Foreign Tenant Dish', false);

    expect(collect([$visible->id])->all())->not->toContain((int) $inactive->id, (int) $archived->id, (int) $otherBranch->id, (int) $foreign->id);
});

it('searches workspace menu items by localized names and escapes LIKE wildcard input', function (): void {
    $record = orderWorkspacePickerUser('tenant-a', 'waiter-a', ['orders.take']);

    app()->setLocale('en');
    orderWorkspacePickerActingIn($record, 0, 'workspace-picker-search');
    $order = orderWorkspacePickerOrder($record, 0);
    $category = orderWorkspacePickerCategory('Search Menu', 'Search Category')['category'];
    orderWorkspacePickerItem($category, $record['branches'][0], 'Khash', hy: 'Խաշ', ru: 'Хаш');
    orderWorkspacePickerItem($category, $record['branches'][0], 'Borscht', hy: 'Բորշչ', ru: 'Борщ');
    orderWorkspacePickerItem($category, $record['branches'][0], 'Burger', hy: 'Բուրգեր', ru: 'Бургер');
    orderWorkspacePickerItem($category, $record['branches'][0], '100%_\\ Sauce');
    orderWorkspacePickerItem($category, $record['branches'][0], '100XY Sauce');

    Livewire::actingAs($record['user'])
        ->test(OrderWorkspaceComponent::class, ['orderId' => (int) $order->id])
        ->set('menuSearch', 'Խաշ')
        ->assertSee('Khash', false)
        ->assertDontSee('Borscht', false)
        ->set('menuSearch', 'Борщ')
        ->assertSee('Borscht', false)
        ->assertDontSee('Khash', false)
        ->set('menuSearch', 'Burger')
        ->assertSee('Burger', false)
        ->assertDontSee('Borscht', false)
        ->set('menuSearch', '%_\\')
        ->assertSee('100%_\\ Sauce', false)
        ->assertDontSee('100XY Sauce', false);
});

it('filters by category and normalizes invalid or foreign categories without leaking them', function (): void {
    $tenantA = orderWorkspacePickerUser('tenant-a', 'waiter-a', ['orders.take']);
    $tenantB = orderWorkspacePickerUser('tenant-b', 'waiter-b', ['orders.take']);

    orderWorkspacePickerActingIn($tenantA, 0, 'workspace-picker-category-a');
    $order = orderWorkspacePickerOrder($tenantA, 0);
    $breakfast = orderWorkspacePickerCategory('Tenant A Menu', 'Breakfast')['category'];
    $lunch = orderWorkspacePickerCategory('Tenant A Lunch Menu', 'Lunch')['category'];
    $empty = orderWorkspacePickerCategory('Tenant A Empty Menu', 'Empty')['category'];
    orderWorkspacePickerItem($breakfast, $tenantA['branches'][0], 'Breakfast Dish');
    orderWorkspacePickerItem($lunch, $tenantA['branches'][0], 'Lunch Dish');

    orderWorkspacePickerActingIn($tenantB, 0, 'workspace-picker-category-b');
    $foreignCategory = orderWorkspacePickerCategory('Foreign Menu', 'Foreign Category')['category'];
    orderWorkspacePickerItem($foreignCategory, $tenantB['branches'][0], 'Foreign Dish');

    orderWorkspacePickerActingIn($tenantA, 0, 'workspace-picker-category-render');

    Livewire::actingAs($tenantA['user'])
        ->test(OrderWorkspaceComponent::class, ['orderId' => (int) $order->id])
        ->call('selectMenuCategory', (int) $breakfast->id)
        ->assertSet('menuCategoryId', (int) $breakfast->id)
        ->assertSee('Breakfast Dish', false)
        ->assertDontSee('Lunch Dish', false)
        ->call('selectMenuCategory', (int) $empty->id)
        ->assertSet('menuCategoryId', null)
        ->assertSee('Breakfast Dish', false)
        ->assertSee('Lunch Dish', false)
        ->call('selectMenuCategory', (int) $foreignCategory->id)
        ->assertSet('menuCategoryId', null)
        ->assertSee('Breakfast Dish', false)
        ->assertSee('Lunch Dish', false)
        ->assertDontSee('Foreign Dish', false);
});

it('escapes menu item and category names while formatting item prices through MoneyFormatter', function (): void {
    $record = orderWorkspacePickerUser('tenant-a', 'waiter-a', ['orders.take']);

    app()->setLocale('en');
    orderWorkspacePickerActingIn($record, 0, 'workspace-picker-escaping');
    $order = orderWorkspacePickerOrder($record, 0);
    $category = orderWorkspacePickerCategory('<script>alert("root")</script>', '<img src=x onerror=alert(1)>')['category'];
    orderWorkspacePickerItem($category, $record['branches'][0], '<script>alert("item")</script>', priceMinor: 123456);

    Livewire::actingAs($record['user'])
        ->test(OrderWorkspaceComponent::class, ['orderId' => (int) $order->id])
        ->assertDontSee('<script>alert("root")</script>', false)
        ->assertDontSee('<img src=x onerror=alert(1)>', false)
        ->assertDontSee('<script>alert("item")</script>', false)
        ->assertSee(e('<script>alert("root")</script>'), false)
        ->assertSee(e('<img src=x onerror=alert(1)>'), false)
        ->assertSee(e('<script>alert("item")</script>'), false)
        ->assertSee(MoneyFormatter::format(new Money(123456, 'AMD'), 'en'), false);
});

it('paginates read only picker items without adding mutation affordances', function (): void {
    $record = orderWorkspacePickerUser('tenant-a', 'waiter-a', ['orders.take']);

    orderWorkspacePickerActingIn($record, 0, 'workspace-picker-pagination');
    $order = orderWorkspacePickerOrder($record, 0);
    $category = orderWorkspacePickerCategory('Paged Menu', 'Paged Category')['category'];

    for ($index = 1; $index <= 13; $index++) {
        orderWorkspacePickerItem($category, $record['branches'][0], sprintf('Paged Dish %02d', $index), sortOrder: $index);
    }

    Livewire::actingAs($record['user'])
        ->test(OrderWorkspaceComponent::class, ['orderId' => (int) $order->id])
        ->assertSet('menuPage', 1)
        ->assertSee('Paged Dish 01', false)
        ->assertDontSee('Paged Dish 13', false)
        ->call('nextMenuPage')
        ->assertSet('menuPage', 2)
        ->assertSee('Paged Dish 13', false)
        ->assertDontSee('Paged Dish 01', false)
        ->assertDontSee('<form', false)
        ->assertDontSee('type="number"', false)
        ->assertDontSee('wire:submit', false)
        ->assertDontSee('openOrder', false)
        ->assertDontSee('AddItem', false);
});

it('keeps Orders workspace and Menu picker query counts fixed as menu result sizes grow', function (): void {
    $record = orderWorkspacePickerUser('tenant-a', 'waiter-a', ['orders.take']);

    orderWorkspacePickerActingIn($record, 0, 'workspace-picker-query-count');
    $order = orderWorkspacePickerOrder($record, 0);
    $category = orderWorkspacePickerCategory('Query Menu', 'Query Category')['category'];

    for ($index = 1; $index <= 30; $index++) {
        orderWorkspacePickerItem($category, $record['branches'][0], "Query Dish {$index}", sortOrder: $index);
    }

    [, $workspaceQueryCount] = orderWorkspacePickerQueryCount(
        fn () => app(FindOrderWorkspace::class)((int) $order->id),
    );
    [$smallMenu, $smallMenuQueryCount] = orderWorkspacePickerQueryCount(
        fn () => app(MenuCatalog::class)->browseSellableInBranch((int) $record['branches'][0]->id, perPage: 5, categoryPerPage: 5),
    );
    [$largeMenu, $largeMenuQueryCount] = orderWorkspacePickerQueryCount(
        fn () => app(MenuCatalog::class)->browseSellableInBranch((int) $record['branches'][0]->id, perPage: 30, categoryPerPage: 30),
    );

    expect($smallMenu->items)->toHaveCount(5)
        ->and($largeMenu->items)->toHaveCount(30)
        ->and($workspaceQueryCount)->toBe(3)
        ->and($smallMenuQueryCount)->toBe($largeMenuQueryCount)
        ->and($smallMenuQueryCount)->toBeLessThanOrEqual(5);
});

/**
 * @param  list<string>  $permissionCodes
 * @return array{tenant: Tenant, branches: list<Branch>, user: User}
 */
function orderWorkspacePickerUser(string $tenantSlug, string $username, array $permissionCodes, int $branchCount = 1): array
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
function orderWorkspacePickerActingIn(array $record, int $branchIndex, string $requestId): void
{
    app(TenantResolver::class)->set((int) $record['tenant']->id);
    app(BranchContext::class)->set((int) $record['branches'][$branchIndex]->id);
    auth()->login($record['user']);
    LogContext::start($requestId, 'orders');
}

/**
 * @param  array{tenant: Tenant, branches: list<Branch>, user: User}  $record
 */
function orderWorkspacePickerOrder(array $record, int $branchIndex): Order
{
    $table = orderWorkspacePickerTable($record['branches'][$branchIndex]);

    return Order::query()->create([
        'branch_id' => (int) $table->branch_id,
        'type' => 'dine_in',
        'status' => 'open',
        'table_id' => (int) $table->id,
        'waiter_id' => (int) $record['user']->id,
        'opened_at' => now()->subMinutes(15),
        'closed_at' => null,
        'client_count' => 1,
        'comment' => null,
        'subtotal_minor' => 0,
        'discount_minor' => 0,
        'total_minor' => 0,
        'currency' => 'AMD',
    ]);
}

function orderWorkspacePickerTable(Branch $branch): Table
{
    $hall = Hall::query()->create([
        'branch_id' => (int) $branch->id,
        'translated_name' => orderWorkspacePickerTranslations('Picker Hall'),
        'color' => '#5FA8D3',
        'sort_order' => 10,
        'active' => true,
    ]);

    return Table::query()->create([
        'branch_id' => (int) $branch->id,
        'hall_id' => (int) $hall->id,
        'translated_name' => orderWorkspacePickerTranslations('Picker Table'),
        'type' => 'standard',
        'shape' => 'square',
        'hdm_department' => 1,
        'is_delivery' => false,
        'sort_order' => 10,
        'active' => true,
    ]);
}

/**
 * @return array{root: MenuCategory, category: MenuCategory}
 */
function orderWorkspacePickerCategory(string $rootName, string $categoryName): array
{
    $root = app(CreateMenuCategory::class)(orderWorkspacePickerText($rootName));
    $category = app(CreateMenuCategory::class)(
        orderWorkspacePickerText($categoryName),
        parentId: (int) $root->id,
    );

    return [
        'root' => $root,
        'category' => $category,
    ];
}

function orderWorkspacePickerItem(
    MenuCategory $category,
    Branch $branch,
    string $en,
    ?string $hy = null,
    ?string $ru = null,
    int $priceMinor = 123400,
    int $sortOrder = 0,
    bool $active = true,
): MenuItem {
    app(BranchContext::class)->set((int) $branch->id);

    return app(CreateMenuItem::class)(
        (int) $category->id,
        orderWorkspacePickerText($en, $hy, $ru),
        null,
        new Money($priceMinor, 'AMD'),
        sortOrder: $sortOrder,
        active: $active,
    );
}

function orderWorkspacePickerText(string $en, ?string $hy = null, ?string $ru = null): LocalizedText
{
    return LocalizedText::fromArray([
        'hy' => $hy ?? $en,
        'ru' => $ru ?? $en,
        'en' => $en,
    ]);
}

/**
 * @return array{hy: string, ru: string, en: string}
 */
function orderWorkspacePickerTranslations(string $text): array
{
    return [
        'hy' => $text,
        'ru' => $text,
        'en' => $text,
    ];
}

/**
 * @template TReturn
 *
 * @param  callable(): TReturn  $callback
 * @return array{0: TReturn, 1: int}
 */
function orderWorkspacePickerQueryCount(callable $callback): array
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
