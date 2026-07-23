<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Models\Permission;
use App\Modules\Identity\Infrastructure\Models\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Identity\Infrastructure\Models\UserBranchAssignment;
use App\Modules\Menu\Application\ArchiveMenuCategory;
use App\Modules\Menu\Application\CreateMenuCategory;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Modules\Tenancy\Infrastructure\Models\Branch;
use App\Modules\Tenancy\Infrastructure\Models\Tenant;
use App\Support\I18n\LocalizedText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

afterEach(function (): void {
    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();
});

it('returns parent category option response for category managers', function (): void {
    $manager = menuCategoryOptionsEndpointUser('tenant-a', 'category-manager', ['menu.categories.manage']);

    app(TenantResolver::class)->set((int) $manager['tenant']->id);
    app(BranchContext::class)->set((int) $manager['branch']->id);

    $root = app(CreateMenuCategory::class)(menuCategoryOptionsEndpointText('Root menu'));
    $otherRoot = app(CreateMenuCategory::class)(menuCategoryOptionsEndpointText('Other root'));
    $subcategory = app(CreateMenuCategory::class)(menuCategoryOptionsEndpointText('Subcategory'), parentId: (int) $root->id);
    $archivedRoot = app(CreateMenuCategory::class)(menuCategoryOptionsEndpointText('Archived root'));

    app(ArchiveMenuCategory::class)((int) $archivedRoot->id);
    app(TenantResolver::class)->clear();
    app(BranchContext::class)->clear();

    $this->actingAs($manager['user'])
        ->withSession(['branch_id' => (int) $manager['branch']->id])
        ->getJson(route('admin.menu.category-options.parents', [
            'exclude_id' => (int) $otherRoot->id,
            'q' => 'root',
        ]))
        ->assertOk()
        ->assertJsonStructure([
            'options' => [
                '*' => ['id', 'label'],
            ],
            'has_more',
            'next_page',
        ])
        ->assertJsonPath('options.0.id', (int) $root->id)
        ->assertJsonPath('options.0.label', 'Root menu')
        ->assertJsonPath('has_more', false)
        ->assertJsonPath('next_page', null)
        ->assertJsonMissing(['id' => (int) $otherRoot->id])
        ->assertJsonMissing(['id' => (int) $subcategory->id])
        ->assertJsonMissing(['id' => (int) $archivedRoot->id]);
});

it('returns item category option response for item managers and hides foreign tenant records', function (): void {
    $tenantA = menuCategoryOptionsEndpointUser('tenant-a', 'item-manager-a', ['menu.items.manage']);
    $tenantB = menuCategoryOptionsEndpointUser('tenant-b', 'item-manager-b', ['menu.items.manage']);

    app(TenantResolver::class)->set((int) $tenantA['tenant']->id);
    app(BranchContext::class)->set((int) $tenantA['branch']->id);

    $rootA = app(CreateMenuCategory::class)(menuCategoryOptionsEndpointText('Tenant A root'));
    $subcategoryA = app(CreateMenuCategory::class)(menuCategoryOptionsEndpointText('Tenant A breakfast'), parentId: (int) $rootA->id);

    app(TenantResolver::class)->set((int) $tenantB['tenant']->id);
    app(BranchContext::class)->set((int) $tenantB['branch']->id);

    $rootB = app(CreateMenuCategory::class)(menuCategoryOptionsEndpointText('Tenant B root'));
    $subcategoryB = app(CreateMenuCategory::class)(menuCategoryOptionsEndpointText('Tenant B breakfast'), parentId: (int) $rootB->id);

    app(TenantResolver::class)->clear();
    app(BranchContext::class)->clear();

    $this->actingAs($tenantA['user'])
        ->withSession(['branch_id' => (int) $tenantA['branch']->id])
        ->getJson(route('admin.menu.category-options.item-categories', ['q' => 'breakfast']))
        ->assertOk()
        ->assertJsonPath('options.0.id', (int) $subcategoryA->id)
        ->assertJsonPath('options.0.label', 'Tenant A root / Tenant A breakfast')
        ->assertJsonPath('has_more', false)
        ->assertJsonPath('next_page', null)
        ->assertJsonMissing(['id' => (int) $rootA->id])
        ->assertJsonMissing(['id' => (int) $subcategoryB->id]);
});

it('requires the same permissions as category and item forms for option endpoints', function (): void {
    $categoryManager = menuCategoryOptionsEndpointUser('tenant-a', 'category-manager', ['menu.categories.manage']);
    $itemManager = menuCategoryOptionsEndpointUser('tenant-b', 'item-manager', ['menu.items.manage']);
    $waiter = menuCategoryOptionsEndpointUser('tenant-c', 'waiter', []);

    $this->actingAs($categoryManager['user'])
        ->withSession(['branch_id' => (int) $categoryManager['branch']->id])
        ->getJson(route('admin.menu.category-options.parents'))
        ->assertOk();

    $this->actingAs($itemManager['user'])
        ->withSession(['branch_id' => (int) $itemManager['branch']->id])
        ->getJson(route('admin.menu.category-options.item-categories'))
        ->assertOk();

    $this->actingAs($itemManager['user'])
        ->withSession(['branch_id' => (int) $itemManager['branch']->id])
        ->getJson(route('admin.menu.category-options.parents'))
        ->assertForbidden();

    $this->actingAs($categoryManager['user'])
        ->withSession(['branch_id' => (int) $categoryManager['branch']->id])
        ->getJson(route('admin.menu.category-options.item-categories'))
        ->assertForbidden();

    $this->actingAs($waiter['user'])
        ->withSession(['branch_id' => (int) $waiter['branch']->id])
        ->getJson(route('admin.menu.category-options.parents'))
        ->assertForbidden();
});

it('returns an empty option list for searches without matches', function (): void {
    $manager = menuCategoryOptionsEndpointUser('tenant-a', 'item-manager', ['menu.items.manage']);

    app(TenantResolver::class)->set((int) $manager['tenant']->id);
    app(BranchContext::class)->set((int) $manager['branch']->id);

    $root = app(CreateMenuCategory::class)(menuCategoryOptionsEndpointText('Menu'));
    app(CreateMenuCategory::class)(menuCategoryOptionsEndpointText('Breakfast'), parentId: (int) $root->id);

    app(TenantResolver::class)->clear();
    app(BranchContext::class)->clear();

    $this->actingAs($manager['user'])
        ->withSession(['branch_id' => (int) $manager['branch']->id])
        ->getJson(route('admin.menu.category-options.item-categories', ['q' => 'zzzz-no-match']))
        ->assertOk()
        ->assertJsonPath('options', [])
        ->assertJsonPath('has_more', false)
        ->assertJsonPath('next_page', null);
});

/**
 * @param  list<string>  $permissionCodes
 * @return array{tenant: Tenant, branch: Branch, user: User}
 */
function menuCategoryOptionsEndpointUser(string $tenantSlug, string $username, array $permissionCodes): array
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

function menuCategoryOptionsEndpointText(string $en): LocalizedText
{
    return LocalizedText::fromArray([
        'hy' => $en,
        'ru' => $en,
        'en' => $en,
    ]);
}
