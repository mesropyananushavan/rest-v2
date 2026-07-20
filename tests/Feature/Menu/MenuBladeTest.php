<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Models\Permission;
use App\Modules\Identity\Infrastructure\Models\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Identity\Infrastructure\Models\UserBranchAssignment;
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
    $manager = menuBladeUser('tenant-a', 'manager-a', ['menu.categories.manage', 'menu.items.manage'], superadmin: true);

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

    $category = MenuCategory::query()->firstOrFail();

    $this->actingAs($manager['user'])
        ->withSession(['branch_id' => (int) $manager['branch']->id])
        ->get(route('admin.menu.categories.edit', ['category' => (int) $category->id]))
        ->assertOk()
        ->assertSee('Breakfast', false);

    $this->actingAs($manager['user'])
        ->withSession(['branch_id' => (int) $manager['branch']->id])
        ->put(route('admin.menu.categories.update', ['category' => (int) $category->id]), menuBladeCategoryPayload('Morning menu', active: false))
        ->assertRedirect(route('admin.menu.index'));

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
        ->get(route('admin.menu.index'))
        ->assertOk()
        ->assertSee('delete_category_', false)
        ->assertSee('delete_item_', false);

    $this->actingAs($manager['user'])
        ->withSession(['branch_id' => (int) $manager['branch']->id])
        ->delete(route('admin.menu.items.destroy', ['item' => (int) $item->id]))
        ->assertRedirect(route('admin.menu.index'));

    $this->actingAs($manager['user'])
        ->withSession(['branch_id' => (int) $manager['branch']->id])
        ->delete(route('admin.menu.categories.destroy', ['category' => (int) $category->id]))
        ->assertRedirect(route('admin.menu.index'));

    expect(MenuItem::query()->count())->toBe(0)
        ->and(MenuCategory::query()->count())->toBe(0);
});

it('requires superadmin for menu deletes even when the user has menu permissions', function (): void {
    $manager = menuBladeUser('tenant-a', 'manager-a', ['menu.categories.manage', 'menu.items.manage']);
    $records = menuBladeRecords($manager, 'Breakfast');

    $this->actingAs($manager['user'])
        ->withSession(['branch_id' => (int) $manager['branch']->id])
        ->get(route('admin.menu.index'))
        ->assertOk()
        ->assertDontSee('delete_category_', false)
        ->assertDontSee('delete_item_', false)
        ->assertDontSee(__('menu.actions.delete'), false);

    $this->actingAs($manager['user'])
        ->withSession(['branch_id' => (int) $manager['branch']->id])
        ->delete(route('admin.menu.items.destroy', ['item' => (int) $records['item']->id]))
        ->assertForbidden();

    $this->actingAs($manager['user'])
        ->withSession(['branch_id' => (int) $manager['branch']->id])
        ->delete(route('admin.menu.categories.destroy', ['category' => (int) $records['category']->id]))
        ->assertForbidden();

    app(TenantResolver::class)->set((int) $manager['tenant']->id);
    app(BranchContext::class)->set((int) $manager['branch']->id);

    expect(MenuItem::query()->count())->toBe(1)
        ->and(MenuCategory::query()->count())->toBe(1);
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
});

it('protects every delete route with the superadmin delete middleware', function (): void {
    $deleteRoutes = [];

    foreach (Route::getRoutes() as $route) {
        if (in_array('DELETE', $route->methods(), true)) {
            $deleteRoutes[] = $route;
        }
    }

    expect($deleteRoutes)->not->toBeEmpty();

    /** @var RoutingRoute $route */
    foreach ($deleteRoutes as $route) {
        expect($route->gatherMiddleware())->toContain('superadmin.delete');
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

    $category = app(CreateMenuCategory::class)(menuBladeText($name));
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
function menuBladeCategoryPayload(string $name, bool $active = true): array
{
    return [
        'name_hy' => $name,
        'name_ru' => $name,
        'name_en' => $name,
        'sort_order' => 0,
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
