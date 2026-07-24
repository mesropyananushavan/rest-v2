<?php

declare(strict_types=1);

use App\Livewire\Admin\MenuIndex;
use App\Modules\Identity\Infrastructure\Models\Permission;
use App\Modules\Identity\Infrastructure\Models\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Identity\Infrastructure\Models\UserBranchAssignment;
use App\Modules\Menu\Application\ArchiveMenuItem;
use App\Modules\Menu\Application\CreateMenuCategory;
use App\Modules\Menu\Application\CreateMenuItem;
use App\Modules\Menu\Infrastructure\Models\MenuCategory;
use App\Modules\Menu\Infrastructure\Models\MenuItem;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Modules\Tenancy\Infrastructure\Models\Branch;
use App\Modules\Tenancy\Infrastructure\Models\Tenant;
use App\Support\I18n\LocalizedText;
use App\Support\Money\Money;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

uses(RefreshDatabase::class);

afterEach(function (): void {
    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();
});

it('renders the menu index route through the Livewire master detail component', function (): void {
    $records = menuIndexLivewireRecords();

    $this->actingAs($records['user'])
        ->withSession(['branch_id' => (int) $records['branch']->id])
        ->get(route('admin.menu.index'))
        ->assertOk()
        ->assertSeeLivewire(MenuIndex::class)
        ->assertSee(__('menu.index.heading'), false)
        ->assertSee('Menu', false)
        ->assertSee('Breakfast', false)
        ->assertSee('Lori Omelette', false)
        ->assertSee('menu-item-placeholder.svg', false);
});

it('searches globally by localized item name and renders the empty search state', function (): void {
    $records = menuIndexLivewireRecords();

    app(TenantResolver::class)->set((int) $records['tenant']->id);
    app(BranchContext::class)->set((int) $records['branch']->id);

    Livewire::actingAs($records['user'])
        ->test(MenuIndex::class)
        ->set('search', 'ձվածեղ')
        ->assertSee(__('menu.search.results_heading'), false)
        ->assertSee('Lori Omelette', false)
        ->assertDontSee('Hidden Soup', false)
        ->set('search', 'does-not-exist')
        ->assertSee(__('menu.empty.search_title'), false)
        ->assertSee(__('menu.actions.reset_search'), false);
});

it('characterizes current search and category context semantics', function (): void {
    $records = menuIndexLivewireRecords();

    app(TenantResolver::class)->set((int) $records['tenant']->id);
    app(BranchContext::class)->set((int) $records['branch']->id);

    $dinnerRoot = app(CreateMenuCategory::class)(menuIndexLivewireText('Dinner group'), sortOrder: 30);
    $dinner = app(CreateMenuCategory::class)(menuIndexLivewireText('Dinner'), sortOrder: 31, parentId: (int) $dinnerRoot->id);
    app(CreateMenuItem::class)(
        (int) $dinner->id,
        menuIndexLivewireText('Selected Trout'),
        null,
        new Money(320000, 'AMD'),
    );
    $empty = app(CreateMenuCategory::class)(menuIndexLivewireText('Empty category'), sortOrder: 32, parentId: (int) $dinnerRoot->id);

    Livewire::withQueryParams([
        'category' => (int) $dinner->id,
        'q' => 'ձվածեղ',
    ])
        ->actingAs($records['user'])
        ->test(MenuIndex::class)
        ->assertSet('category', (int) $dinner->id)
        ->assertSee(__('menu.search.results_heading'), false)
        ->assertSee('Lori Omelette', false)
        ->assertDontSee('Selected Trout', false)
        ->call('clearSearch')
        ->assertSet('category', (int) $dinner->id)
        ->assertSee('Selected Trout', false)
        ->assertDontSee(__('menu.search.results_heading'), false);

    Livewire::withQueryParams([])
        ->actingAs($records['user'])
        ->test(MenuIndex::class)
        ->assertSet('category', (int) $records['category']->id)
        ->assertSee('Breakfast', false)
        ->assertSee('Lori Omelette', false);

    Livewire::withQueryParams(['category' => (int) $empty->id])
        ->actingAs($records['user'])
        ->test(MenuIndex::class)
        ->assertSet('category', (int) $empty->id)
        ->assertSee('Empty category', false)
        ->assertSee(__('menu.empty.no_items_title'), false)
        ->assertDontSee('Lori Omelette', false);
});

it('keeps menu index category render query count independent of rendered result size', function (): void {
    $records = menuIndexLivewireRecords();

    app(TenantResolver::class)->set((int) $records['tenant']->id);
    app(BranchContext::class)->set((int) $records['branch']->id);

    $bulkRoot = app(CreateMenuCategory::class)(menuIndexLivewireText('Bulk group'), sortOrder: 30);
    $bulk = app(CreateMenuCategory::class)(menuIndexLivewireText('Bulk category'), sortOrder: 31, parentId: (int) $bulkRoot->id);

    for ($index = 1; $index <= 30; $index++) {
        app(CreateMenuItem::class)(
            (int) $bulk->id,
            menuIndexLivewireText("Bulk Visible Dish {$index}"),
            null,
            new Money(120000 + $index, 'AMD'),
            sortOrder: $index,
        );
    }

    $smallQueryCount = menuIndexLivewireRenderQueryCount(
        $records['user'],
        ['category' => (int) $records['category']->id],
        fn (Testable $component): Testable => $component
            ->assertSee('Lori Omelette', false)
            ->assertDontSee('Bulk Visible Dish 25', false),
    );
    $largeQueryCount = menuIndexLivewireRenderQueryCount(
        $records['user'],
        ['category' => (int) $bulk->id],
        fn (Testable $component): Testable => $component
            ->assertSee('Bulk Visible Dish 1', false)
            ->assertSee('Bulk Visible Dish 25', false)
            ->assertDontSee('Bulk Visible Dish 30', false),
    );

    expect($smallQueryCount)->toBe($largeQueryCount)
        ->and($smallQueryCount)->toBe(10);
});

it('keeps menu index search render query count independent of rendered result size', function (): void {
    $records = menuIndexLivewireRecords();

    app(TenantResolver::class)->set((int) $records['tenant']->id);
    app(BranchContext::class)->set((int) $records['branch']->id);

    $bulkRoot = app(CreateMenuCategory::class)(menuIndexLivewireText('Search bulk group'), sortOrder: 30);
    $bulk = app(CreateMenuCategory::class)(menuIndexLivewireText('Search bulk category'), sortOrder: 31, parentId: (int) $bulkRoot->id);
    app(CreateMenuItem::class)(
        (int) $bulk->id,
        menuIndexLivewireText('Single Needle Dish'),
        null,
        new Money(120000, 'AMD'),
    );

    for ($index = 1; $index <= 30; $index++) {
        app(CreateMenuItem::class)(
            (int) $bulk->id,
            menuIndexLivewireText("Bulk Needle Dish {$index}"),
            null,
            new Money(130000 + $index, 'AMD'),
            sortOrder: $index,
        );
    }

    $smallQueryCount = menuIndexLivewireRenderQueryCount(
        $records['user'],
        ['q' => 'single needle'],
        fn (Testable $component): Testable => $component
            ->assertSee(__('menu.search.results_heading'), false)
            ->assertSee('Single Needle Dish', false)
            ->assertDontSee('Bulk Needle Dish 1', false),
    );
    $largeQueryCount = menuIndexLivewireRenderQueryCount(
        $records['user'],
        ['q' => 'bulk needle'],
        fn (Testable $component): Testable => $component
            ->assertSee(__('menu.search.results_heading'), false)
            ->assertSee('Bulk Needle Dish 1', false)
            ->assertSee('Bulk Needle Dish 25', false)
            ->assertDontSee('Bulk Needle Dish 30', false),
    );

    expect($smallQueryCount)->toBe($largeQueryCount)
        ->and($smallQueryCount)->toBe(13);
});

it('uses the category URL state and filters the category panel search', function (): void {
    $records = menuIndexLivewireRecords();

    app(TenantResolver::class)->set((int) $records['tenant']->id);
    app(BranchContext::class)->set((int) $records['branch']->id);

    $dinnerRoot = app(CreateMenuCategory::class)(menuIndexLivewireText('Dinner group'), sortOrder: 30);
    $dinner = app(CreateMenuCategory::class)(menuIndexLivewireText('Dinner'), sortOrder: 31, parentId: (int) $dinnerRoot->id);
    app(CreateMenuItem::class)(
        (int) $dinner->id,
        menuIndexLivewireText('Grilled Trout'),
        null,
        new Money(320000, 'AMD'),
    );

    Livewire::withQueryParams(['category' => (int) $dinner->id])
        ->actingAs($records['user'])
        ->test(MenuIndex::class)
        ->assertSet('category', (int) $dinner->id)
        ->assertSee('Dinner', false)
        ->assertSee('Grilled Trout', false)
        ->assertDontSee('Lori Omelette', false)
        ->set('categorySearch', 'Break')
        ->assertSee('Breakfast', false);
});

it('normalizes root category URL state to the first selectable subcategory', function (): void {
    $records = menuIndexLivewireRecords();

    app(TenantResolver::class)->set((int) $records['tenant']->id);
    app(BranchContext::class)->set((int) $records['branch']->id);

    Livewire::withQueryParams(['category' => (int) $records['root']->id])
        ->actingAs($records['user'])
        ->test(MenuIndex::class)
        ->assertSet('category', (int) $records['category']->id)
        ->assertSee('Breakfast', false)
        ->assertSee('Lori Omelette', false);
});

it('keeps an empty root category as a valid unselected URL state', function (): void {
    $records = menuIndexLivewireRecords();

    app(TenantResolver::class)->set((int) $records['tenant']->id);
    app(BranchContext::class)->set((int) $records['branch']->id);

    $emptyRoot = app(CreateMenuCategory::class)(menuIndexLivewireText('Empty Root'), sortOrder: 20);

    Livewire::withQueryParams(['category' => (int) $emptyRoot->id])
        ->actingAs($records['user'])
        ->test(MenuIndex::class)
        ->assertSet('category', null)
        ->assertSee('Empty Root', false)
        ->assertSee(__('menu.empty.no_categories_title'), false)
        ->assertDontSee('Lori Omelette', false);
});

it('renders empty root categories without making them selectable', function (): void {
    $records = menuIndexLivewireRecords();

    app(TenantResolver::class)->set((int) $records['tenant']->id);
    app(BranchContext::class)->set((int) $records['branch']->id);

    $emptyRoot = app(CreateMenuCategory::class)(menuIndexLivewireText('Empty Root'), sortOrder: 20);

    Livewire::actingAs($records['user'])
        ->test(MenuIndex::class)
        ->assertSee('Empty Root', false)
        ->assertSee(__('menu.empty.no_subcategories_title'), false)
        ->assertSee(route('admin.menu.categories.create', ['parent_id' => (int) $emptyRoot->id]), false)
        ->assertDontSee('wire:click="selectCategory('.(int) $emptyRoot->id.')"', false);
});

it('finds empty root categories through category search', function (): void {
    $records = menuIndexLivewireRecords();

    app(TenantResolver::class)->set((int) $records['tenant']->id);
    app(BranchContext::class)->set((int) $records['branch']->id);

    app(CreateMenuCategory::class)(menuIndexLivewireText('Standalone Dessert Root'), sortOrder: 20);

    Livewire::actingAs($records['user'])
        ->test(MenuIndex::class)
        ->set('categorySearch', 'Dessert')
        ->assertSee('Standalone Dessert Root', false)
        ->assertSee(__('menu.empty.no_subcategories_title'), false)
        ->assertDontSee('wire:click="selectCategory('.(int) $records['category']->id.')"', false);
});

it('paginates the category panel by roots and keeps empty roots visible on later pages', function (): void {
    $records = menuIndexLivewireRecords();

    app(TenantResolver::class)->set((int) $records['tenant']->id);
    app(BranchContext::class)->set((int) $records['branch']->id);

    for ($index = 1; $index <= 30; $index++) {
        app(CreateMenuCategory::class)(menuIndexLivewireText("Empty Root {$index}"), sortOrder: $index);
    }

    Livewire::actingAs($records['user'])
        ->test(MenuIndex::class)
        ->call('nextCategoryPage')
        ->assertSet('categoryPage', 2)
        ->assertSee('Empty Root 25', false)
        ->assertSee(__('menu.empty.no_subcategories_title'), false);
});

it('normalizes archive visibility for managers and exposes archive maintenance to superadmins', function (): void {
    $records = menuIndexLivewireRecords();
    $owner = menuIndexLivewireUser(
        (int) $records['tenant']->id,
        (int) $records['branch']->id,
        'owner-menu-index',
        ['menu.categories.manage', 'menu.items.manage'],
        superadmin: true,
    );

    app(TenantResolver::class)->set((int) $records['tenant']->id);
    app(BranchContext::class)->set((int) $records['branch']->id);

    app(ArchiveMenuItem::class)((int) $records['item']->id);

    Livewire::withQueryParams(['archive_mode' => 'archived'])
        ->actingAs($records['user'])
        ->test(MenuIndex::class)
        ->assertSet('archiveMode', 'active')
        ->assertDontSee('Lori Omelette', false)
        ->assertDontSee(__('menu.archive_modes.archived'), false)
        ->assertDontSee(__('menu.archive_modes.all'), false)
        ->assertDontSee(__('menu.actions.restore'), false)
        ->assertDontSee(__('menu.actions.force_delete'), false);

    Livewire::withQueryParams(['archive_mode' => 'all'])
        ->actingAs($records['user'])
        ->test(MenuIndex::class)
        ->assertSet('archiveMode', 'active')
        ->assertDontSee('Lori Omelette', false);

    Livewire::withQueryParams(['archive_mode' => 'archived'])
        ->actingAs($owner)
        ->test(MenuIndex::class)
        ->assertSet('archiveMode', 'archived')
        ->assertSee('Lori Omelette', false)
        ->assertDontSee('Hidden Soup', false)
        ->assertSee(__('menu.status.archived'), false)
        ->assertSee(__('menu.actions.restore'), false)
        ->assertSee(__('menu.actions.force_delete'), false);

    Livewire::withQueryParams(['archive_mode' => 'all', 'show_inactive' => '1'])
        ->actingAs($owner)
        ->test(MenuIndex::class)
        ->assertSet('archiveMode', 'all')
        ->assertSee('Lori Omelette', false)
        ->assertSee('Hidden Soup', false);
});

it('toggles item activity inline in both directions', function (): void {
    $records = menuIndexLivewireRecords();

    app(TenantResolver::class)->set((int) $records['tenant']->id);
    app(BranchContext::class)->set((int) $records['branch']->id);

    $component = Livewire::withQueryParams(['show_inactive' => '1'])
        ->actingAs($records['user'])
        ->test(MenuIndex::class)
        ->assertSee(__('menu.actions.deactivate'), false)
        ->call('toggleItemActivity', (int) $records['item']->id)
        ->assertSee(__('menu.flash.item_deactivated'), false)
        ->assertSee(__('menu.actions.activate'), false);

    expect(MenuItem::query()->findOrFail((int) $records['item']->id)->active)->toBeFalse();

    $component
        ->call('toggleItemActivity', (int) $records['item']->id)
        ->assertSee(__('menu.flash.item_activated'), false)
        ->assertSee(__('menu.actions.deactivate'), false);

    expect(MenuItem::query()->findOrFail((int) $records['item']->id)->active)->toBeTrue();
});

it('removes a deactivated item from the default active list after inline toggle', function (): void {
    $records = menuIndexLivewireRecords();

    app(TenantResolver::class)->set((int) $records['tenant']->id);
    app(BranchContext::class)->set((int) $records['branch']->id);

    Livewire::actingAs($records['user'])
        ->test(MenuIndex::class)
        ->assertSet('showInactive', false)
        ->assertSee('Lori Omelette', false)
        ->call('toggleItemActivity', (int) $records['item']->id)
        ->assertSee(__('menu.flash.item_deactivated'), false)
        ->assertDontSee('Lori Omelette', false);
});

it('forbids inline item activity toggle without menu item permission', function (): void {
    $records = menuIndexLivewireRecords();
    $waiter = menuIndexLivewireUser(
        (int) $records['tenant']->id,
        (int) $records['branch']->id,
        'waiter-menu-index',
        [],
    );

    app(TenantResolver::class)->set((int) $records['tenant']->id);
    app(BranchContext::class)->set((int) $records['branch']->id);

    Livewire::actingAs($waiter)
        ->test(MenuIndex::class)
        ->call('toggleItemActivity', (int) $records['item']->id)
        ->assertStatus(403);

    expect(MenuItem::query()->findOrFail((int) $records['item']->id)->active)->toBeTrue();
});

it('returns 404 when inline item activity toggle targets another tenant item', function (): void {
    $tenantA = menuIndexLivewireRecords();
    $tenantB = menuIndexLivewireRecordsFor('tenant-b-menu-index', 'Tenant B', 'foreign-menu-index');

    app(TenantResolver::class)->set((int) $tenantA['tenant']->id);
    app(BranchContext::class)->set((int) $tenantA['branch']->id);

    expect(fn () => Livewire::actingAs($tenantA['user'])
        ->test(MenuIndex::class)
        ->call('toggleItemActivity', (int) $tenantB['item']->id))
        ->toThrow(ModelNotFoundException::class);

    app(TenantResolver::class)->set((int) $tenantB['tenant']->id);

    expect(MenuItem::query()->findOrFail((int) $tenantB['item']->id)->active)->toBeTrue();
});

it('does not expose or allow inline item activity toggle for archived items', function (): void {
    $records = menuIndexLivewireRecords();
    $owner = menuIndexLivewireUser(
        (int) $records['tenant']->id,
        (int) $records['branch']->id,
        'owner-menu-index-toggle',
        ['menu.categories.manage', 'menu.items.manage'],
        superadmin: true,
    );

    app(TenantResolver::class)->set((int) $records['tenant']->id);
    app(BranchContext::class)->set((int) $records['branch']->id);
    app(ArchiveMenuItem::class)((int) $records['item']->id);

    Livewire::withQueryParams(['archive_mode' => 'archived'])
        ->actingAs($owner)
        ->test(MenuIndex::class)
        ->assertSee('Lori Omelette', false)
        ->assertDontSee(__('menu.actions.deactivate'), false)
        ->assertDontSee(__('menu.actions.activate'), false);

    expect(fn () => Livewire::actingAs($owner)
        ->test(MenuIndex::class)
        ->call('toggleItemActivity', (int) $records['item']->id))
        ->toThrow(ModelNotFoundException::class);
});

/**
 * @return array{tenant: Tenant, branch: Branch, user: User, root: MenuCategory, category: MenuCategory, item: MenuItem}
 */
function menuIndexLivewireRecords(): array
{
    return menuIndexLivewireRecordsFor('tenant-a-menu-index', 'Tenant A', 'manager-menu-index');
}

/**
 * @return array{tenant: Tenant, branch: Branch, user: User, root: MenuCategory, category: MenuCategory, item: MenuItem}
 */
function menuIndexLivewireRecordsFor(string $tenantSlug, string $tenantName, string $username): array
{
    $tenant = Tenant::query()->create([
        'name' => $tenantName,
        'slug' => $tenantSlug,
        'default_locale' => 'en',
        'currency' => 'AMD',
        'status' => 'active',
    ]);

    app(TenantResolver::class)->set((int) $tenant->id);

    $branch = Branch::query()->create([
        'name' => 'Tenant A Branch',
        'timezone' => 'Asia/Yerevan',
        'status' => 'active',
    ]);

    app(BranchContext::class)->set((int) $branch->id);

    $root = app(CreateMenuCategory::class)(menuIndexLivewireText('Menu'), sortOrder: 0);
    $category = app(CreateMenuCategory::class)(menuIndexLivewireText('Breakfast'), sortOrder: 10, parentId: (int) $root->id);
    $item = app(CreateMenuItem::class)(
        (int) $category->id,
        menuIndexLivewireText('Lori Omelette', 'Լոռի ձվածեղ', 'Лорийский омлет'),
        menuIndexLivewireText('Eggs and local cheese'),
        new Money(220000, 'AMD'),
    );
    app(CreateMenuItem::class)(
        (int) $category->id,
        menuIndexLivewireText('Hidden Soup'),
        null,
        new Money(120000, 'AMD'),
        sortOrder: 20,
        active: false,
    );

    $user = menuIndexLivewireUser(
        (int) $tenant->id,
        (int) $branch->id,
        $username,
        ['menu.categories.manage', 'menu.items.manage'],
    );

    return [
        'tenant' => $tenant,
        'branch' => $branch,
        'user' => $user,
        'root' => $root,
        'category' => $category,
        'item' => $item,
    ];
}

/**
 * @param  list<string>  $permissionCodes
 */
function menuIndexLivewireUser(int $tenantId, int $branchId, string $username, array $permissionCodes, bool $superadmin = false): User
{
    app(TenantResolver::class)->set($tenantId);

    $role = Role::query()->create([
        'code' => "{$username}-role",
        'name' => "{$username} Role",
    ]);

    $permissions = collect($permissionCodes)
        ->map(fn (string $code): Permission => Permission::query()->firstOrCreate(
            ['code' => $code],
            ['name' => $code],
        ));

    $role->permissions()->attach(
        $permissions->pluck('id')->all(),
        ['tenant_id' => $tenantId],
    );

    $user = User::query()->create([
        'role_id' => (int) $role->id,
        'name' => $username,
        'email' => "{$username}@smartrest.test",
        'username' => $username,
        'default_locale' => 'en',
        'active' => true,
        'is_superadmin' => $superadmin,
        'password' => Hash::make('password'),
    ]);

    UserBranchAssignment::query()->create([
        'user_id' => (int) $user->id,
        'branch_id' => $branchId,
    ]);

    return $user;
}

function menuIndexLivewireText(string $en, ?string $hy = null, ?string $ru = null): LocalizedText
{
    return LocalizedText::fromArray([
        'hy' => $hy ?? $en,
        'ru' => $ru ?? $en,
        'en' => $en,
    ]);
}

/**
 * @param  array<string, mixed>  $queryParams
 * @param  callable(Testable<MenuIndex>): Testable<MenuIndex>  $assertions
 */
function menuIndexLivewireRenderQueryCount(User $user, array $queryParams, callable $assertions): int
{
    $user->loadMissing('role.permissions');

    DB::flushQueryLog();
    DB::enableQueryLog();

    try {
        /** @var Testable<MenuIndex> $component */
        $component = Livewire::withQueryParams($queryParams)
            ->actingAs($user)
            ->test(MenuIndex::class);
        $assertions($component);

        return count(DB::getQueryLog());
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }
}
