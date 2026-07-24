<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Models\Permission;
use App\Modules\Identity\Infrastructure\Models\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Identity\Infrastructure\Models\UserBranchAssignment;
use App\Modules\Menu\Http\Resources\MenuItemResource;
use App\Modules\Menu\Infrastructure\Models\MenuCategory;
use App\Modules\Menu\Infrastructure\Models\MenuItem;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Modules\Tenancy\Infrastructure\Models\Branch;
use App\Modules\Tenancy\Infrastructure\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

afterEach(function (): void {
    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();
});

it('keeps load test marker columns out of menu model public surfaces', function (): void {
    $records = menuMarkerExposureRecords();

    app(TenantResolver::class)->set((int) $records['tenant']->id);
    app(BranchContext::class)->set((int) $records['branch']->id);

    $category = MenuCategory::query()->findOrFail((int) $records['category']->id);
    $item = MenuItem::query()->findOrFail((int) $records['item']->id);

    expect($category->getFillable())->not->toContain('load_test_key')
        ->and($item->getFillable())->not->toContain('load_test_key')
        ->and($category->getCasts())->not->toHaveKey('load_test_key')
        ->and($item->getCasts())->not->toHaveKey('load_test_key')
        ->and($category->getAppends())->not->toContain('load_test_key')
        ->and($item->getAppends())->not->toContain('load_test_key')
        ->and($category->toArray())->not->toHaveKey('load_test_key')
        ->and($item->toArray())->not->toHaveKey('load_test_key')
        ->and(MenuItemResource::make($item, 'en'))->not->toHaveKey('load_test_key');
});

it('excludes load test marker columns from menu mass assignment', function (): void {
    $category = new MenuCategory;
    $category->fill([
        'tenant_id' => 123,
        'translated_name' => menuMarkerExposureText('Category'),
        'sort_order' => 1,
        'active' => true,
        'load_test_key' => 'category-marker-leak',
    ]);

    $item = new MenuItem;
    $item->fill([
        'tenant_id' => 123,
        'branch_id' => 456,
        'category_id' => 789,
        'translated_name' => menuMarkerExposureText('Item'),
        'translated_description' => null,
        'price_minor' => 1000,
        'currency' => 'AMD',
        'sort_order' => 1,
        'active' => true,
        'load_test_key' => 'item-marker-leak',
    ]);

    expect($category->getAttributes())->not->toHaveKey('load_test_key')
        ->and($item->getAttributes())->not->toHaveKey('load_test_key');
});

it('does not expose load test markers through menu api responses or rendered views', function (): void {
    $records = menuMarkerExposureRecords();

    $apiResponse = $this->actingAs($records['user'])
        ->withSession(['branch_id' => (int) $records['branch']->id])
        ->getJson('/api/v1/menu-items?category_id='.(int) $records['category']->id);

    $apiResponse
        ->assertOk()
        ->assertJsonPath('meta.pagination.total', 1);

    expect($apiResponse->json('data.0'))->not->toHaveKey('load_test_key');

    $this->actingAs($records['user'])
        ->withSession(['branch_id' => (int) $records['branch']->id])
        ->get(route('admin.menu.index'))
        ->assertOk()
        ->assertSee('Marker Visible Dish', false)
        ->assertDontSee('category-marker-secret', false)
        ->assertDontSee('item-marker-secret', false);
});

/**
 * @return array{tenant: Tenant, branch: Branch, user: User, category: MenuCategory, item: MenuItem}
 */
function menuMarkerExposureRecords(): array
{
    $tenant = Tenant::query()->create([
        'name' => 'Marker Tenant',
        'slug' => 'marker-tenant',
        'default_locale' => 'hy',
        'currency' => 'AMD',
        'status' => 'active',
    ]);

    app(TenantResolver::class)->set((int) $tenant->id);

    $branch = Branch::query()->create([
        'name' => 'Marker Branch',
        'timezone' => 'Asia/Yerevan',
        'status' => 'active',
    ]);

    $role = Role::query()->create([
        'code' => 'marker-manager',
        'name' => 'Marker Manager',
    ]);

    $permission = Permission::query()->create([
        'code' => 'menu.items.manage',
        'name' => 'Manage menu items',
    ]);

    $role->permissions()->attach((int) $permission->id, ['tenant_id' => (int) $tenant->id]);

    $user = User::query()->create([
        'role_id' => (int) $role->id,
        'name' => 'Marker Manager',
        'email' => 'marker-manager@smartrest.test',
        'username' => 'marker-manager',
        'default_locale' => 'en',
        'active' => true,
        'password' => Hash::make('password'),
    ]);

    UserBranchAssignment::query()->create([
        'user_id' => (int) $user->id,
        'branch_id' => (int) $branch->id,
    ]);

    app(BranchContext::class)->set((int) $branch->id);

    $root = MenuCategory::query()->create([
        'translated_name' => menuMarkerExposureText('Marker Root'),
        'sort_order' => 1,
        'active' => true,
    ]);

    $category = MenuCategory::query()->create([
        'parent_id' => (int) $root->id,
        'translated_name' => menuMarkerExposureText('Marker Category'),
        'sort_order' => 1,
        'active' => true,
    ]);

    $item = MenuItem::query()->create([
        'branch_id' => (int) $branch->id,
        'category_id' => (int) $category->id,
        'translated_name' => menuMarkerExposureText('Marker Visible Dish'),
        'translated_description' => null,
        'price_minor' => 120000,
        'currency' => 'AMD',
        'sort_order' => 1,
        'active' => true,
    ]);

    DB::table('menu_categories')
        ->whereKey((int) $category->id)
        ->update(['load_test_key' => 'category-marker-secret']);
    DB::table('menu_items')
        ->whereKey((int) $item->id)
        ->update(['load_test_key' => 'item-marker-secret']);

    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();

    return [
        'tenant' => $tenant,
        'branch' => $branch,
        'user' => $user,
        'category' => $category,
        'item' => $item,
    ];
}

/**
 * @return array{hy: string, ru: string, en: string}
 */
function menuMarkerExposureText(string $value): array
{
    return [
        'hy' => "{$value} HY",
        'ru' => "{$value} RU",
        'en' => $value,
    ];
}
