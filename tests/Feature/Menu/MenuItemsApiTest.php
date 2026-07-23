<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Models\Permission;
use App\Modules\Identity\Infrastructure\Models\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Identity\Infrastructure\Models\UserBranchAssignment;
use App\Modules\Menu\Infrastructure\Models\MenuCategory;
use App\Modules\Menu\Infrastructure\Models\MenuItem;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Modules\Tenancy\Infrastructure\Models\Branch;
use App\Modules\Tenancy\Infrastructure\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\Fluent\AssertableJson;

uses(RefreshDatabase::class);

it('returns documented JSON 401 instead of redirecting guests', function (): void {
    $this->withHeader('X-Request-Id', 'api-guest-request')
        ->getJson('/api/v1/menu-items')
        ->assertStatus(401)
        ->assertHeader('X-Request-Id', 'api-guest-request')
        ->assertHeaderMissing('Location')
        ->assertJson(fn (AssertableJson $json): AssertableJson => $json
            ->where('errors.0.code', 'auth.unauthenticated')
            ->where('errors.0.field', null)
            ->whereType('errors.0.message', 'string')
            ->where('meta.request_id', 'api-guest-request')
        );
});

it('returns documented JSON 403 for authenticated users without menu item permission', function (): void {
    $record = menuItemsApiUser('tenant-a', 'waiter-a', []);

    $this->actingAs($record['user'])
        ->withSession(['branch_id' => (int) $record['branches'][0]->id])
        ->getJson('/api/v1/menu-items')
        ->assertStatus(403)
        ->assertJsonPath('errors.0.code', 'auth.forbidden')
        ->assertJsonPath('errors.0.field', null)
        ->assertJsonStructure([
            'errors' => [['code', 'message', 'field']],
            'meta' => ['request_id'],
        ]);
});

it('returns the success envelope with request locale and only current tenant branch active items', function (): void {
    $tenantA = menuItemsApiUser('tenant-a', 'manager-a', ['menu.items.manage'], branchCount: 2);
    $tenantB = menuItemsApiUser('tenant-b', 'manager-b', ['menu.items.manage']);
    $categoryA = menuItemsApiCategory($tenantA, 'Root A', 'Breakfast A');
    $categoryB = menuItemsApiCategory($tenantB, 'Root B', 'Breakfast B');

    $visible = menuItemsApiItem($tenantA, $categoryA, 'Visible omelette', priceMinor: 123450);
    $otherBranch = menuItemsApiItem($tenantA, $categoryA, 'Other branch omelette', branch: $tenantA['branches'][1]);
    $otherTenant = menuItemsApiItem($tenantB, $categoryB, 'Other tenant omelette');
    $archived = menuItemsApiItem($tenantA, $categoryA, 'Archived omelette');
    $archived->delete();
    $inactive = menuItemsApiItem($tenantA, $categoryA, 'Inactive omelette', active: false);

    $response = $this->actingAs($tenantA['user'])
        ->withHeader('X-Request-Id', 'api-menu-items-request')
        ->withSession([
            'branch_id' => (int) $tenantA['branches'][0]->id,
            'locale' => 'ru',
        ])
        ->getJson('/api/v1/menu-items?show_archived=1');

    $response
        ->assertOk()
        ->assertHeader('X-Request-Id', 'api-menu-items-request')
        ->assertJsonPath('meta.request_id', 'api-menu-items-request')
        ->assertJsonPath('meta.locale', 'ru')
        ->assertJsonPath('meta.pagination.total', 1)
        ->assertJsonPath('data.0.id', (int) $visible->id)
        ->assertJsonPath('data.0.category_id', (int) $categoryA->id)
        ->assertJsonPath('data.0.name', 'Visible omelette RU')
        ->assertJsonPath('data.0.price_minor', 123450)
        ->assertJsonPath('data.0.currency', 'AMD')
        ->assertJsonPath('data.0.active', true)
        ->assertJsonPath('data.0.sort_order', 10)
        ->assertJsonMissing(['id' => (int) $otherBranch->id])
        ->assertJsonMissing(['id' => (int) $otherTenant->id])
        ->assertJsonMissing(['id' => (int) $archived->id])
        ->assertJsonMissing(['id' => (int) $inactive->id]);

    expect($response->json('data.0'))->not->toHaveKeys(['price', 'formatted_price', 'internal_image', 'public_image']);
});

it('returns 404 for explicitly requested foreign tenant or foreign branch category filters', function (): void {
    $tenantA = menuItemsApiUser('tenant-a', 'manager-a', ['menu.items.manage'], branchCount: 2);
    $tenantB = menuItemsApiUser('tenant-b', 'manager-b', ['menu.items.manage']);
    $foreignTenantCategory = menuItemsApiCategory($tenantB, 'Root B', 'Breakfast B');
    $foreignBranchCategory = menuItemsApiCategory($tenantA, 'Root A2', 'Branch B breakfast');

    menuItemsApiItem($tenantA, $foreignBranchCategory, 'Branch B only', branch: $tenantA['branches'][1]);

    $this->actingAs($tenantA['user'])
        ->withSession(['branch_id' => (int) $tenantA['branches'][0]->id])
        ->getJson('/api/v1/menu-items?category_id='.(int) $foreignTenantCategory->id)
        ->assertStatus(404)
        ->assertJsonPath('errors.0.code', 'resource.not_found');

    $this->actingAs($tenantA['user'])
        ->withSession(['branch_id' => (int) $tenantA['branches'][0]->id])
        ->getJson('/api/v1/menu-items?category_id='.(int) $foreignBranchCategory->id)
        ->assertStatus(404)
        ->assertJsonPath('errors.0.code', 'resource.not_found');
});

it('returns page pagination metadata across pages and clamps per page to the documented maximum', function (): void {
    $record = menuItemsApiUser('tenant-a', 'manager-a', ['menu.items.manage']);
    $category = menuItemsApiCategory($record, 'Root', 'Breakfast');

    for ($i = 1; $i <= 55; $i++) {
        menuItemsApiItem($record, $category, "Item {$i}", sortOrder: $i);
    }

    $this->actingAs($record['user'])
        ->withSession(['branch_id' => (int) $record['branches'][0]->id])
        ->getJson('/api/v1/menu-items?category_id='.(int) $category->id.'&per_page=2&page=2')
        ->assertOk()
        ->assertJsonPath('meta.pagination.current_page', 2)
        ->assertJsonPath('meta.pagination.per_page', 2)
        ->assertJsonPath('meta.pagination.total', 55)
        ->assertJsonPath('meta.pagination.last_page', 28)
        ->assertJsonPath('meta.pagination.from', 3)
        ->assertJsonPath('meta.pagination.to', 4)
        ->assertJsonPath('meta.pagination.has_more_pages', true);

    $response = $this->actingAs($record['user'])
        ->withSession(['branch_id' => (int) $record['branches'][0]->id])
        ->getJson('/api/v1/menu-items?category_id='.(int) $category->id.'&per_page=999');

    $response
        ->assertOk()
        ->assertJsonPath('meta.pagination.per_page', 50)
        ->assertJsonPath('meta.pagination.total', 55);

    expect($response->json('data'))->toHaveCount(50);
});

it('returns one field-scoped 422 error per invalid query parameter', function (): void {
    $record = menuItemsApiUser('tenant-a', 'manager-a', ['menu.items.manage']);

    $response = $this->actingAs($record['user'])
        ->withSession(['branch_id' => (int) $record['branches'][0]->id])
        ->getJson('/api/v1/menu-items?page=abc&per_page=0&category_id=nope&search[]=bad');

    $response
        ->assertStatus(422)
        ->assertJsonStructure([
            'errors' => [['code', 'message', 'field']],
            'meta' => ['request_id'],
        ]);

    expect(collect($response->json('errors'))->pluck('field')->sort()->values()->all())
        ->toBe(['category_id', 'page', 'per_page', 'search'])
        ->and(collect($response->json('errors'))->pluck('code')->all())
        ->toContain('validation.integer', 'validation.min', 'validation.string');
});

it('renders MenuDomainException as documented JSON without changing the stable code', function (): void {
    $record = menuItemsApiUser('tenant-a', 'manager-a', ['menu.items.manage'], assignedBranchIndexes: []);

    $this->actingAs($record['user'])
        ->getJson('/api/v1/menu-items?search=anything')
        ->assertStatus(422)
        ->assertJsonPath('errors.0.code', 'menu.branch_context_required')
        ->assertJsonPath('errors.0.field', null)
        ->assertJsonStructure([
            'errors' => [['code', 'message', 'field']],
            'meta' => ['request_id'],
        ]);
});

it('keeps existing Blade MenuDomainException flash behaviour for non API requests', function (): void {
    $record = menuItemsApiUser('tenant-a', 'manager-a', ['menu.items.manage']);
    $root = menuItemsApiRootCategory($record, 'Root only');

    $this->actingAs($record['user'])
        ->withSession(['branch_id' => (int) $record['branches'][0]->id])
        ->from(route('admin.menu.items.create'))
        ->post(route('admin.menu.items.store'), [
            'category_id' => (int) $root->id,
            'name_hy' => 'Թեստ',
            'name_ru' => 'Тест',
            'name_en' => 'Test',
            'price_major' => '12.00',
            'currency' => 'AMD',
            'sort_order' => '1',
            'active' => '1',
        ])
        ->assertRedirect(route('admin.menu.items.create'))
        ->assertSessionHasErrors('menu');
});

it('searches globally within the current branch when the search query is present', function (): void {
    $record = menuItemsApiUser('tenant-a', 'manager-a', ['menu.items.manage']);
    $breakfast = menuItemsApiCategory($record, 'Root A', 'Breakfast');
    $lunch = menuItemsApiCategory($record, 'Root B', 'Lunch');
    $match = menuItemsApiItem($record, $lunch, 'Needle soup');
    $miss = menuItemsApiItem($record, $breakfast, 'Omelette');

    $this->actingAs($record['user'])
        ->withSession(['branch_id' => (int) $record['branches'][0]->id])
        ->getJson('/api/v1/menu-items?search=needle&category_id='.(int) $breakfast->id)
        ->assertOk()
        ->assertJsonPath('meta.pagination.total', 1)
        ->assertJsonPath('data.0.id', (int) $match->id)
        ->assertJsonMissing(['id' => (int) $miss->id]);
});

/**
 * @param  list<string>  $permissionCodes
 * @param  list<int>  $assignedBranchIndexes
 * @return array{tenant: Tenant, branches: list<Branch>, role: Role, user: User}
 */
function menuItemsApiUser(
    string $tenantSlug,
    string $username,
    array $permissionCodes,
    int $branchCount = 1,
    array $assignedBranchIndexes = [0],
): array {
    $tenant = Tenant::query()->create([
        'name' => str($tenantSlug)->headline()->toString(),
        'slug' => $tenantSlug,
        'default_locale' => 'hy',
        'currency' => 'AMD',
        'status' => 'active',
    ]);

    app(TenantResolver::class)->set((int) $tenant->id);

    $branches = [];
    for ($i = 1; $i <= $branchCount; $i++) {
        $branches[] = Branch::query()->create([
            'name' => "{$tenantSlug} Branch {$i}",
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
        'password' => Hash::make('password'),
    ]);

    foreach ($assignedBranchIndexes as $branchIndex) {
        UserBranchAssignment::query()->create([
            'user_id' => (int) $user->id,
            'branch_id' => (int) $branches[$branchIndex]->id,
        ]);
    }

    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();

    return [
        'tenant' => $tenant,
        'branches' => $branches,
        'role' => $role,
        'user' => $user,
    ];
}

/**
 * @param  array{tenant: Tenant, branches: list<Branch>, role: Role, user: User}  $record
 */
function menuItemsApiRootCategory(array $record, string $name): MenuCategory
{
    app(TenantResolver::class)->set((int) $record['tenant']->id);

    $root = MenuCategory::query()->create([
        'translated_name' => menuItemsApiText($name),
        'sort_order' => 1,
        'active' => true,
    ]);

    app(TenantResolver::class)->clear();

    return $root;
}

/**
 * @param  array{tenant: Tenant, branches: list<Branch>, role: Role, user: User}  $record
 */
function menuItemsApiCategory(array $record, string $rootName, string $subcategoryName): MenuCategory
{
    app(TenantResolver::class)->set((int) $record['tenant']->id);

    $root = MenuCategory::query()->create([
        'translated_name' => menuItemsApiText($rootName),
        'sort_order' => 1,
        'active' => true,
    ]);

    $subcategory = MenuCategory::query()->create([
        'parent_id' => (int) $root->id,
        'translated_name' => menuItemsApiText($subcategoryName),
        'sort_order' => 1,
        'active' => true,
    ]);

    app(TenantResolver::class)->clear();

    return $subcategory;
}

/**
 * @param  array{tenant: Tenant, branches: list<Branch>, role: Role, user: User}  $record
 */
function menuItemsApiItem(
    array $record,
    MenuCategory $category,
    string $name,
    ?Branch $branch = null,
    int $priceMinor = 1000,
    int $sortOrder = 10,
    bool $active = true,
): MenuItem {
    $branch ??= $record['branches'][0];
    app(TenantResolver::class)->set((int) $record['tenant']->id);
    app(BranchContext::class)->set((int) $branch->id);

    $item = MenuItem::query()->create([
        'branch_id' => (int) $branch->id,
        'category_id' => (int) $category->id,
        'translated_name' => menuItemsApiText($name),
        'translated_description' => null,
        'price_minor' => $priceMinor,
        'currency' => 'AMD',
        'sort_order' => $sortOrder,
        'active' => $active,
    ]);

    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();

    return $item;
}

/**
 * @return array{hy: string, ru: string, en: string}
 */
function menuItemsApiText(string $value): array
{
    return [
        'hy' => "{$value} HY",
        'ru' => "{$value} RU",
        'en' => $value,
    ];
}
