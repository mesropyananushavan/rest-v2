<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Models\Permission;
use App\Modules\Identity\Infrastructure\Models\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Identity\Infrastructure\Models\UserBranchAssignment;
use App\Modules\Menu\Application\ArchiveMenuCategory;
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
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

afterEach(function (): void {
    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();
});

it('runs menu category and item CRUD through authenticated Blade routes', function (): void {
    $manager = menuBladeUser('tenant-a', 'manager-a', ['menu.categories.manage', 'menu.items.manage']);

    $this->actingAs($manager['user'])
        ->withSession(['branch_id' => (int) $manager['branch']->id])
        ->get(route('admin.menu.index'))
        ->assertOk()
        ->assertSee(__('menu.index.heading'), false)
        ->assertSee(__('menu.empty.categories'), false);

    $this->actingAs($manager['user'])
        ->withSession(['branch_id' => (int) $manager['branch']->id])
        ->post(route('admin.menu.categories.store'), menuBladeCategoryPayload('Breakfast'))
        ->assertRedirect(route('admin.menu.index'));

    app(TenantResolver::class)->set((int) $manager['tenant']->id);
    app(BranchContext::class)->set((int) $manager['branch']->id);

    $rootCategory = MenuCategory::query()->firstOrFail();

    $this->actingAs($manager['user'])
        ->withSession(['branch_id' => (int) $manager['branch']->id])
        ->get(route('admin.menu.categories.edit', ['category' => (int) $rootCategory->id]))
        ->assertOk()
        ->assertSee('Breakfast', false);

    $this->actingAs($manager['user'])
        ->withSession(['branch_id' => (int) $manager['branch']->id])
        ->put(route('admin.menu.categories.update', ['category' => (int) $rootCategory->id]), menuBladeCategoryPayload('Morning menu', active: false))
        ->assertRedirect(route('admin.menu.index'));

    $this->actingAs($manager['user'])
        ->withSession(['branch_id' => (int) $manager['branch']->id])
        ->post(route('admin.menu.categories.store'), menuBladeCategoryPayload('Breakfast plates', parentId: (int) $rootCategory->id))
        ->assertRedirect(route('admin.menu.index'));

    $category = MenuCategory::query()->whereNotNull('parent_id')->firstOrFail();

    $this->actingAs($manager['user'])
        ->withSession(['branch_id' => (int) $manager['branch']->id])
        ->post(route('admin.menu.items.store'), menuBladeItemPayload((int) $category->id, 'Omelette'))
        ->assertRedirect(route('admin.menu.index'));

    $item = MenuItem::query()->firstOrFail();

    $this->actingAs($manager['user'])
        ->withSession(['branch_id' => (int) $manager['branch']->id])
        ->get(route('admin.menu.items.edit', ['item' => (int) $item->id]))
        ->assertOk()
        ->assertSee('Omelette', false);

    $this->actingAs($manager['user'])
        ->withSession(['branch_id' => (int) $manager['branch']->id])
        ->put(route('admin.menu.items.update', ['item' => (int) $item->id]), menuBladeItemPayload((int) $category->id, 'Cheese omelette', active: false))
        ->assertRedirect(route('admin.menu.index'));

    expect(MenuItem::query()->firstOrFail()->translatedName()->forLocale('en'))->toBe('Cheese omelette');

    $this->actingAs($manager['user'])
        ->withSession(['branch_id' => (int) $manager['branch']->id])
        ->get(route('admin.menu.index', ['show_inactive' => '1']))
        ->assertOk()
        ->assertSee('archive_category_', false)
        ->assertSee('archive_item_', false)
        ->assertSee(__('menu.actions.archive'), false);

    $this->actingAs($manager['user'])
        ->withSession(['branch_id' => (int) $manager['branch']->id])
        ->delete(route('admin.menu.items.destroy', ['item' => (int) $item->id]))
        ->assertRedirect(route('admin.menu.index'));

    $this->actingAs($manager['user'])
        ->withSession(['branch_id' => (int) $manager['branch']->id])
        ->delete(route('admin.menu.categories.destroy', ['category' => (int) $category->id]))
        ->assertRedirect(route('admin.menu.index'));

    expect(MenuItem::query()->count())->toBe(0)
        ->and(MenuCategory::query()->pluck('id')->all())->toBe([(int) $rootCategory->id]);
});

it('renders root-only parent options on the category create form', function (): void {
    $manager = menuBladeUser('tenant-a', 'manager-a', ['menu.categories.manage']);

    app(TenantResolver::class)->set((int) $manager['tenant']->id);
    $root = app(CreateMenuCategory::class)(menuBladeText('Root menu'), sortOrder: 10);
    $subcategory = app(CreateMenuCategory::class)(menuBladeText('Breakfast plates'), parentId: (int) $root->id);
    $archivedRoot = app(CreateMenuCategory::class)(menuBladeText('Archived root'), sortOrder: 20);
    app(ArchiveMenuCategory::class)((int) $archivedRoot->id);

    app(TenantResolver::class)->clear();

    $this->actingAs($manager['user'])
        ->withSession(['branch_id' => (int) $manager['branch']->id])
        ->get(route('admin.menu.categories.create'))
        ->assertOk()
        ->assertSee('name="parent_id"', false)
        ->assertSee(__('menu.fields.parent_category'), false)
        ->assertSee(__('menu.categories.root_parent_option'), false)
        ->assertSee('Root menu', false)
        ->assertDontSee('Breakfast plates', false)
        ->assertDontSee('Archived root', false);
});

it('renders the current parent on the category edit form without offering the category itself', function (): void {
    $manager = menuBladeUser('tenant-a', 'manager-a', ['menu.categories.manage']);

    app(TenantResolver::class)->set((int) $manager['tenant']->id);
    $root = app(CreateMenuCategory::class)(menuBladeText('Root menu'), sortOrder: 10);
    $otherRoot = app(CreateMenuCategory::class)(menuBladeText('Other root'), sortOrder: 20);
    $subcategory = app(CreateMenuCategory::class)(menuBladeText('Breakfast plates'), parentId: (int) $root->id);

    app(TenantResolver::class)->clear();

    $this->actingAs($manager['user'])
        ->withSession(['branch_id' => (int) $manager['branch']->id])
        ->get(route('admin.menu.categories.edit', ['category' => (int) $subcategory->id]))
        ->assertOk()
        ->assertSee('name="parent_id"', false)
        ->assertSee('value="'.(int) $root->id.'"', false)
        ->assertSee('Root menu', false)
        ->assertSee('Other root', false)
        ->assertSee('exclude_id='.(int) $subcategory->id, false);
});

it('creates a subcategory through the category form parent selector payload', function (): void {
    $manager = menuBladeUser('tenant-a', 'manager-a', ['menu.categories.manage']);

    app(TenantResolver::class)->set((int) $manager['tenant']->id);
    $root = app(CreateMenuCategory::class)(menuBladeText('Root menu'), sortOrder: 10);
    app(TenantResolver::class)->clear();

    $this->actingAs($manager['user'])
        ->withSession(['branch_id' => (int) $manager['branch']->id])
        ->post(route('admin.menu.categories.store'), menuBladeCategoryPayload('Breakfast plates', parentId: (int) $root->id))
        ->assertRedirect(route('admin.menu.index'));

    app(TenantResolver::class)->set((int) $manager['tenant']->id);

    expect(MenuCategory::query()
        ->where('translated_name->en', 'Breakfast plates')
        ->firstOrFail()
        ->parent_id)->toBe((int) $root->id);
});

it('keeps the selected parent when editing a subcategory through the category form', function (): void {
    $manager = menuBladeUser('tenant-a', 'manager-a', ['menu.categories.manage']);

    app(TenantResolver::class)->set((int) $manager['tenant']->id);
    $root = app(CreateMenuCategory::class)(menuBladeText('Root menu'), sortOrder: 10);
    $subcategory = app(CreateMenuCategory::class)(menuBladeText('Breakfast plates'), parentId: (int) $root->id);
    app(TenantResolver::class)->clear();

    $this->actingAs($manager['user'])
        ->withSession(['branch_id' => (int) $manager['branch']->id])
        ->put(route('admin.menu.categories.update', ['category' => (int) $subcategory->id]), menuBladeCategoryPayload('Breakfast plates updated', parentId: (int) $root->id))
        ->assertRedirect(route('admin.menu.index'));

    app(TenantResolver::class)->set((int) $manager['tenant']->id);

    expect(MenuCategory::query()->findOrFail((int) $subcategory->id)->parent_id)->toBe((int) $root->id);
});

it('keeps an existing category parent when a legacy update payload omits parent_id', function (): void {
    $manager = menuBladeUser('tenant-a', 'manager-a', ['menu.categories.manage']);

    app(TenantResolver::class)->set((int) $manager['tenant']->id);
    $root = app(CreateMenuCategory::class)(menuBladeText('Root menu'), sortOrder: 10);
    $subcategory = app(CreateMenuCategory::class)(menuBladeText('Breakfast plates'), parentId: (int) $root->id);
    app(TenantResolver::class)->clear();

    $payload = menuBladeCategoryPayload('Breakfast plates updated');
    unset($payload['parent_id']);

    $this->actingAs($manager['user'])
        ->withSession(['branch_id' => (int) $manager['branch']->id])
        ->put(route('admin.menu.categories.update', ['category' => (int) $subcategory->id]), $payload)
        ->assertRedirect(route('admin.menu.index'));

    app(TenantResolver::class)->set((int) $manager['tenant']->id);

    expect(MenuCategory::query()->findOrFail((int) $subcategory->id)->parent_id)->toBe((int) $root->id);
});

it('allows managers to archive and requires superadmin to restore menu records', function (): void {
    $manager = menuBladeUser('tenant-a', 'manager-a', ['menu.categories.manage', 'menu.items.manage']);
    $records = menuBladeRecords($manager, 'Breakfast');

    $this->actingAs($manager['user'])
        ->withSession(['branch_id' => (int) $manager['branch']->id])
        ->get(route('admin.menu.index'))
        ->assertOk()
        ->assertSee('Breakfast', false)
        ->assertSee('archive_category_', false)
        ->assertSee('archive_item_', false)
        ->assertDontSee(__('menu.actions.restore'), false);

    $this->actingAs($manager['user'])
        ->withSession(['branch_id' => (int) $manager['branch']->id])
        ->delete(route('admin.menu.items.destroy', ['item' => (int) $records['item']->id]))
        ->assertRedirect(route('admin.menu.index'));

    app(TenantResolver::class)->set((int) $manager['tenant']->id);
    app(BranchContext::class)->set((int) $manager['branch']->id);

    expect(MenuItem::query()->count())->toBe(0)
        ->and(MenuItem::withTrashed()->findOrFail((int) $records['item']->id)->trashed())->toBeTrue();

    $this->actingAs($manager['user'])
        ->withSession(['branch_id' => (int) $manager['branch']->id])
        ->get(route('admin.menu.index'))
        ->assertOk()
        ->assertDontSee(__('menu.archive_modes.archived'), false)
        ->assertDontSee(__('menu.archive_modes.all'), false)
        ->assertDontSee('Breakfast Item', false);

    $this->actingAs($manager['user'])
        ->withSession(['branch_id' => (int) $manager['branch']->id])
        ->get(route('admin.menu.index', ['archive_mode' => 'archived']))
        ->assertOk()
        ->assertDontSee('Breakfast Item', false)
        ->assertDontSee(__('menu.status.archived'), false)
        ->assertDontSee(__('menu.archive_modes.archived'), false)
        ->assertDontSee(__('menu.archive_modes.all'), false)
        ->assertDontSee(__('menu.actions.restore'), false)
        ->assertDontSee(__('menu.actions.force_delete'), false);

    $this->actingAs($manager['user'])
        ->withSession(['branch_id' => (int) $manager['branch']->id])
        ->post(route('admin.menu.items.restore', ['item' => (int) $records['item']->id]))
        ->assertForbidden();

    $this->actingAs($manager['user'])
        ->withSession(['branch_id' => (int) $manager['branch']->id])
        ->delete(route('admin.menu.items.force-delete', ['item' => (int) $records['item']->id]))
        ->assertForbidden();

    $manager['user']->forceFill(['is_superadmin' => true])->save();

    $this->actingAs($manager['user']->refresh())
        ->withSession(['branch_id' => (int) $manager['branch']->id])
        ->get(route('admin.menu.index', ['archive_mode' => 'archived']))
        ->assertOk()
        ->assertSee('Breakfast Item', false)
        ->assertSee(__('menu.status.archived'), false)
        ->assertSee(__('menu.actions.restore'), false)
        ->assertSee(__('menu.actions.force_delete'), false);

    $this->actingAs($manager['user'])
        ->withSession(['branch_id' => (int) $manager['branch']->id])
        ->post(route('admin.menu.items.restore', ['item' => (int) $records['item']->id]))
        ->assertRedirect(route('admin.menu.index', ['archive_mode' => 'archived']));

    expect(MenuItem::query()->count())->toBe(1);

    $manager['user']->forceFill(['is_superadmin' => false])->save();

    $this->actingAs($manager['user'])
        ->withSession(['branch_id' => (int) $manager['branch']->id])
        ->delete(route('admin.menu.categories.destroy', ['category' => (int) $records['category']->id]))
        ->assertRedirect(route('admin.menu.index'));

    app(TenantResolver::class)->set((int) $manager['tenant']->id);
    app(BranchContext::class)->set((int) $manager['branch']->id);

    expect(MenuItem::query()->count())->toBe(0)
        ->and(MenuCategory::query()->count())->toBe(1)
        ->and(MenuCategory::withTrashed()->findOrFail((int) $records['category']->id)->trashed())->toBeTrue()
        ->and(MenuItem::withTrashed()->findOrFail((int) $records['item']->id)->archived_with_category_id)->toBe((int) $records['category']->id);

    $this->actingAs($manager['user']->refresh())
        ->withSession(['branch_id' => (int) $manager['branch']->id])
        ->post(route('admin.menu.categories.restore', ['category' => (int) $records['category']->id]))
        ->assertForbidden();

    $this->actingAs($manager['user'])
        ->withSession(['branch_id' => (int) $manager['branch']->id])
        ->delete(route('admin.menu.categories.force-delete', ['category' => (int) $records['category']->id]))
        ->assertForbidden();

    $manager['user']->forceFill(['is_superadmin' => true])->save();

    $this->actingAs($manager['user']->refresh())
        ->withSession(['branch_id' => (int) $manager['branch']->id])
        ->post(route('admin.menu.categories.restore', ['category' => (int) $records['category']->id]))
        ->assertRedirect(route('admin.menu.index', ['archive_mode' => 'archived']));

    expect(MenuCategory::query()->count())->toBe(2)
        ->and(MenuItem::query()->count())->toBe(1);
});

it('force deletes archived menu items and categories for superadmins only', function (): void {
    $owner = menuBladeUser('tenant-a', 'owner-a', ['menu.categories.manage', 'menu.items.manage'], superadmin: true);
    $itemRecords = menuBladeRecords($owner, 'Item archive');

    $this->actingAs($owner['user'])
        ->withSession(['branch_id' => (int) $owner['branch']->id])
        ->delete(route('admin.menu.items.destroy', ['item' => (int) $itemRecords['item']->id]))
        ->assertRedirect(route('admin.menu.index'));

    $this->actingAs($owner['user'])
        ->withSession(['branch_id' => (int) $owner['branch']->id])
        ->get(route('admin.menu.index', ['archive_mode' => 'archived']))
        ->assertOk()
        ->assertSee('force_delete_item_', false)
        ->assertSee(__('menu.actions.force_delete'), false)
        ->assertSee(__('menu.confirm.force_delete_item_message'), false);

    $this->actingAs($owner['user'])
        ->withSession(['branch_id' => (int) $owner['branch']->id])
        ->delete(route('admin.menu.items.force-delete', ['item' => (int) $itemRecords['item']->id]))
        ->assertRedirect(route('admin.menu.index', ['archive_mode' => 'archived']));

    app(TenantResolver::class)->set((int) $owner['tenant']->id);
    app(BranchContext::class)->set((int) $owner['branch']->id);

    expect(MenuItem::withTrashed()->find((int) $itemRecords['item']->id))->toBeNull()
        ->and(MenuCategory::query()->find((int) $itemRecords['category']->id))->not->toBeNull();

    $categoryRecords = menuBladeRecords($owner, 'Category archive');

    $this->actingAs($owner['user'])
        ->withSession(['branch_id' => (int) $owner['branch']->id])
        ->delete(route('admin.menu.categories.destroy', ['category' => (int) $categoryRecords['category']->id]))
        ->assertRedirect(route('admin.menu.index'));

    $this->actingAs($owner['user'])
        ->withSession(['branch_id' => (int) $owner['branch']->id])
        ->get(route('admin.menu.index', ['archive_mode' => 'archived']))
        ->assertOk()
        ->assertSee('force_delete_category_', false)
        ->assertSee(__('menu.confirm.force_delete_category_message'), false);

    $this->actingAs($owner['user'])
        ->withSession(['branch_id' => (int) $owner['branch']->id])
        ->delete(route('admin.menu.categories.force-delete', ['category' => (int) $categoryRecords['category']->id]))
        ->assertRedirect(route('admin.menu.index', ['archive_mode' => 'archived']));

    app(TenantResolver::class)->set((int) $owner['tenant']->id);
    app(BranchContext::class)->set((int) $owner['branch']->id);

    expect(MenuCategory::withTrashed()->find((int) $categoryRecords['category']->id))->toBeNull()
        ->and(MenuItem::withTrashed()->find((int) $categoryRecords['item']->id))->toBeNull();
});

it('does not show archived categories in item forms or accept them in item creation', function (): void {
    $manager = menuBladeUser('tenant-a', 'manager-a', ['menu.categories.manage', 'menu.items.manage']);
    $records = menuBladeRecords($manager, 'Breakfast');

    $this->actingAs($manager['user'])
        ->withSession(['branch_id' => (int) $manager['branch']->id])
        ->delete(route('admin.menu.categories.destroy', ['category' => (int) $records['category']->id]))
        ->assertRedirect(route('admin.menu.index'));

    $this->actingAs($manager['user'])
        ->withSession(['branch_id' => (int) $manager['branch']->id])
        ->get(route('admin.menu.items.create'))
        ->assertOk()
        ->assertDontSee('Breakfast', false);

    $this->actingAs($manager['user'])
        ->withSession(['branch_id' => (int) $manager['branch']->id])
        ->post(route('admin.menu.items.store'), menuBladeItemPayload((int) $records['category']->id, 'Compromised'))
        ->assertNotFound();
});

it('does not render category actions for item-only managers', function (): void {
    $manager = menuBladeUser('tenant-a', 'manager-a', ['menu.items.manage']);
    menuBladeRecords($manager, 'Breakfast');

    $this->actingAs($manager['user'])
        ->withSession(['branch_id' => (int) $manager['branch']->id])
        ->get(route('admin.menu.index'))
        ->assertOk()
        ->assertSee('Breakfast', false)
        ->assertSee('archive_item_', false)
        ->assertDontSee(__('menu.actions.create_category'), false)
        ->assertDontSee('archive_category_', false);
});

it('returns 403 for authenticated users without menu permissions', function (): void {
    $user = menuBladeUser('tenant-a', 'waiter-a', []);

    $this->actingAs($user['user'])
        ->withSession(['branch_id' => (int) $user['branch']->id])
        ->get(route('admin.menu.index'))
        ->assertForbidden();

    $this->actingAs($user['user'])
        ->withSession(['branch_id' => (int) $user['branch']->id])
        ->post(route('admin.menu.categories.store'), menuBladeCategoryPayload('Breakfast'))
        ->assertForbidden();
});

it('returns 404 when a user requests another tenant menu resource by id', function (): void {
    $tenantA = menuBladeUser('tenant-a', 'manager-a', ['menu.categories.manage', 'menu.items.manage'], superadmin: true);
    $tenantB = menuBladeUser('tenant-b', 'manager-b', ['menu.categories.manage', 'menu.items.manage'], superadmin: true);

    $foreign = menuBladeRecords($tenantB, 'Foreign breakfast');

    $this->actingAs($tenantA['user'])
        ->withSession(['branch_id' => (int) $tenantA['branch']->id])
        ->get(route('admin.menu.categories.edit', ['category' => (int) $foreign['category']->id]))
        ->assertNotFound();

    $this->actingAs($tenantA['user'])
        ->withSession(['branch_id' => (int) $tenantA['branch']->id])
        ->put(route('admin.menu.categories.update', ['category' => (int) $foreign['category']->id]), menuBladeCategoryPayload('Compromised'))
        ->assertNotFound();

    $this->actingAs($tenantA['user'])
        ->withSession(['branch_id' => (int) $tenantA['branch']->id])
        ->delete(route('admin.menu.categories.destroy', ['category' => (int) $foreign['category']->id]))
        ->assertNotFound();

    $this->actingAs($tenantA['user'])
        ->withSession(['branch_id' => (int) $tenantA['branch']->id])
        ->post(route('admin.menu.categories.restore', ['category' => (int) $foreign['category']->id]))
        ->assertNotFound();

    $this->actingAs($tenantA['user'])
        ->withSession(['branch_id' => (int) $tenantA['branch']->id])
        ->delete(route('admin.menu.categories.force-delete', ['category' => (int) $foreign['category']->id]))
        ->assertNotFound();

    $this->actingAs($tenantA['user'])
        ->withSession(['branch_id' => (int) $tenantA['branch']->id])
        ->post(route('admin.menu.items.store'), menuBladeItemPayload((int) $foreign['category']->id, 'Compromised'))
        ->assertNotFound();

    $this->actingAs($tenantA['user'])
        ->withSession(['branch_id' => (int) $tenantA['branch']->id])
        ->get(route('admin.menu.items.edit', ['item' => (int) $foreign['item']->id]))
        ->assertNotFound();

    $this->actingAs($tenantA['user'])
        ->withSession(['branch_id' => (int) $tenantA['branch']->id])
        ->put(route('admin.menu.items.update', ['item' => (int) $foreign['item']->id]), menuBladeItemPayload((int) $foreign['category']->id, 'Compromised'))
        ->assertNotFound();

    $this->actingAs($tenantA['user'])
        ->withSession(['branch_id' => (int) $tenantA['branch']->id])
        ->delete(route('admin.menu.items.destroy', ['item' => (int) $foreign['item']->id]))
        ->assertNotFound();

    $this->actingAs($tenantA['user'])
        ->withSession(['branch_id' => (int) $tenantA['branch']->id])
        ->post(route('admin.menu.items.restore', ['item' => (int) $foreign['item']->id]))
        ->assertNotFound();

    $this->actingAs($tenantA['user'])
        ->withSession(['branch_id' => (int) $tenantA['branch']->id])
        ->delete(route('admin.menu.items.force-delete', ['item' => (int) $foreign['item']->id]))
        ->assertNotFound();
});

it('allows archive routes by permission and protects restore routes with superadmin middleware', function (): void {
    $deleteRoutes = [];
    $restoreRoutes = [];
    $forceDeleteRoutes = [];

    foreach (Route::getRoutes() as $route) {
        if (in_array('DELETE', $route->methods(), true)) {
            $deleteRoutes[] = $route;
        }

        if (str_ends_with((string) $route->getName(), '.restore')) {
            $restoreRoutes[] = $route;
        }

        if (str_ends_with((string) $route->getName(), '.force-delete')) {
            $forceDeleteRoutes[] = $route;
        }
    }

    expect($deleteRoutes)->not->toBeEmpty();
    expect($restoreRoutes)->not->toBeEmpty();
    expect($forceDeleteRoutes)->not->toBeEmpty();

    /** @var RoutingRoute $route */
    foreach ($deleteRoutes as $route) {
        if (str_ends_with((string) $route->getName(), '.force-delete')) {
            continue;
        }

        expect($route->gatherMiddleware())->not->toContain('superadmin');
    }

    /** @var RoutingRoute $route */
    foreach ($restoreRoutes as $route) {
        expect($route->gatherMiddleware())->toContain('superadmin');
    }

    /** @var RoutingRoute $route */
    foreach ($forceDeleteRoutes as $route) {
        expect($route->gatherMiddleware())->toContain('superadmin');
    }
});

/**
 * @param  list<string>  $permissionCodes
 * @return array{tenant: Tenant, branch: Branch, user: User}
 */
function menuBladeUser(string $tenantSlug, string $username, array $permissionCodes, bool $superadmin = false): array
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

    $role = Role::query()->create([
        'code' => "{$username}-role",
        'name' => "{$username} Role",
    ]);

    $permissions = collect($permissionCodes)
        ->map(fn (string $code): Permission => Permission::query()->create([
            'code' => $code,
            'name' => $code,
        ]));

    $role->permissions()->attach(
        $permissions->pluck('id')->all(),
        ['tenant_id' => (int) $tenant->id],
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
        'branch_id' => (int) $branch->id,
    ]);

    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();

    return [
        'tenant' => $tenant,
        'branch' => $branch,
        'user' => $user,
    ];
}

/**
 * @param  array{tenant: Tenant, branch: Branch, user: User}  $user
 * @return array{category: MenuCategory, item: MenuItem}
 */
function menuBladeRecords(array $user, string $name): array
{
    app(TenantResolver::class)->set((int) $user['tenant']->id);
    app(BranchContext::class)->set((int) $user['branch']->id);

    $root = app(CreateMenuCategory::class)(menuBladeText('Menu'), sortOrder: 0);
    $category = app(CreateMenuCategory::class)(menuBladeText($name), parentId: (int) $root->id);
    $item = app(CreateMenuItem::class)(
        (int) $category->id,
        menuBladeText("{$name} Item"),
        null,
        new Money(100000, 'AMD'),
    );

    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();

    return [
        'category' => $category,
        'item' => $item,
    ];
}

/**
 * @return array<string, mixed>
 */
function menuBladeCategoryPayload(string $name, bool $active = true, int $sortOrder = 0, int $parentId = 0): array
{
    return [
        'parent_id' => $parentId,
        'name_hy' => $name,
        'name_ru' => $name,
        'name_en' => $name,
        'sort_order' => $sortOrder,
        'active' => $active ? '1' : '0',
    ];
}

/**
 * @return array<string, mixed>
 */
function menuBladeItemPayload(int $categoryId, string $name, bool $active = true): array
{
    return [
        'category_id' => $categoryId,
        'name_hy' => $name,
        'name_ru' => $name,
        'name_en' => $name,
        'description_hy' => "{$name} description",
        'description_ru' => "{$name} description",
        'description_en' => "{$name} description",
        'price_major' => '1000',
        'currency' => 'AMD',
        'sort_order' => 0,
        'active' => $active ? '1' : '0',
    ];
}

function menuBladeText(string $text): LocalizedText
{
    return LocalizedText::fromArray([
        'hy' => $text,
        'ru' => $text,
        'en' => $text,
    ]);
}
