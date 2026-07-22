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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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

/**
 * @return array{tenant: Tenant, branch: Branch, user: User, category: MenuCategory, item: MenuItem}
 */
function menuIndexLivewireRecords(): array
{
    $tenant = Tenant::query()->create([
        'name' => 'Tenant A',
        'slug' => 'tenant-a-menu-index',
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

    $root = app(CreateMenuCategory::class)(menuIndexLivewireText('Menu'), sortOrder: 100);
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
        'manager-menu-index',
        ['menu.categories.manage', 'menu.items.manage'],
    );

    return [
        'tenant' => $tenant,
        'branch' => $branch,
        'user' => $user,
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
