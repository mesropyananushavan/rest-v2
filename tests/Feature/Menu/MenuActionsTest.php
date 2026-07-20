<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Models\Permission;
use App\Modules\Identity\Infrastructure\Models\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Identity\Infrastructure\Models\UserBranchAssignment;
use App\Modules\Menu\Application\ArchiveMenuCategory;
use App\Modules\Menu\Application\ArchiveMenuItem;
use App\Modules\Menu\Application\CreateMenuCategory;
use App\Modules\Menu\Application\CreateMenuItem;
use App\Modules\Menu\Application\ListMenuCategories;
use App\Modules\Menu\Application\ListMenuItems;
use App\Modules\Menu\Application\RestoreMenuCategory;
use App\Modules\Menu\Application\RestoreMenuItem;
use App\Modules\Menu\Application\UpdateMenuCategory;
use App\Modules\Menu\Application\UpdateMenuItem;
use App\Modules\Menu\Domain\MenuDomainException;
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
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

afterEach(function (): void {
    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();
});

it('creates updates lists archives and restores menu categories and items through application actions', function (): void {
    $tenant = menuActionsTenant('tenant-a', 'Tenant A');

    app(TenantResolver::class)->set((int) $tenant['tenant']->id);
    app(BranchContext::class)->set((int) $tenant['branch']->id);

    $category = app(CreateMenuCategory::class)(
        menuActionsText('Breakfast'),
        sortOrder: 5,
        active: true,
    );

    expect(app(ListMenuCategories::class)()->pluck('id')->all())->toBe([(int) $category->id]);

    $updatedCategory = app(UpdateMenuCategory::class)(
        (int) $category->id,
        menuActionsText('Morning menu'),
        sortOrder: 7,
        active: false,
    );

    expect($updatedCategory->translatedName()->forLocale('en'))->toBe('Morning menu')
        ->and($updatedCategory->sort_order)->toBe(7)
        ->and($updatedCategory->active)->toBeFalse();

    $manuallyArchivedItem = app(CreateMenuItem::class)(
        (int) $category->id,
        menuActionsText('Omelette'),
        menuActionsText('Eggs and greens'),
        new Money(180000, 'AMD'),
        sortOrder: 3,
        active: true,
    );

    $cascadeArchivedItem = app(CreateMenuItem::class)(
        (int) $category->id,
        menuActionsText('Toast'),
        null,
        new Money(90000, 'AMD'),
        sortOrder: 4,
        active: true,
    );

    expect((int) $manuallyArchivedItem->branch_id)->toBe((int) $tenant['branch']->id)
        ->and($manuallyArchivedItem->price()->minor)->toBe(180000)
        ->and(app(ListMenuItems::class)()->pluck('id')->all())->toBe([
            (int) $manuallyArchivedItem->id,
            (int) $cascadeArchivedItem->id,
        ]);

    $updatedItem = app(UpdateMenuItem::class)(
        (int) $manuallyArchivedItem->id,
        (int) $category->id,
        menuActionsText('Cheese omelette'),
        null,
        new Money(210000, 'AMD'),
        sortOrder: 4,
        active: false,
    );

    expect($updatedItem->translatedName()->forLocale('en'))->toBe('Cheese omelette')
        ->and($updatedItem->translatedDescription())->toBeNull()
        ->and($updatedItem->price()->minor)->toBe(210000)
        ->and($updatedItem->active)->toBeFalse();

    app(ArchiveMenuItem::class)((int) $manuallyArchivedItem->id);

    $manuallyArchivedItem = MenuItem::withTrashed()->findOrFail((int) $manuallyArchivedItem->id);

    expect($manuallyArchivedItem->trashed())->toBeTrue()
        ->and($manuallyArchivedItem->archived_with_category_id)->toBeNull()
        ->and(app(ListMenuItems::class)()->pluck('id')->all())->toBe([(int) $cascadeArchivedItem->id]);

    app(ArchiveMenuCategory::class)((int) $category->id);

    $category = MenuCategory::withTrashed()->findOrFail((int) $category->id);
    $cascadeArchivedItem = MenuItem::withTrashed()->findOrFail((int) $cascadeArchivedItem->id);

    expect($category->trashed())->toBeTrue()
        ->and($cascadeArchivedItem->trashed())->toBeTrue()
        ->and($cascadeArchivedItem->archived_with_category_id)->toBe((int) $category->id)
        ->and(app(ListMenuCategories::class)()->pluck('id')->all())->toBe([])
        ->and(app(ListMenuItems::class)()->pluck('id')->all())->toBe([]);

    expect(fn () => app(RestoreMenuItem::class)((int) $cascadeArchivedItem->id))
        ->toThrow(MenuDomainException::class, 'Menu items cannot be restored while their category is archived.');

    app(RestoreMenuCategory::class)((int) $category->id);

    $category = MenuCategory::query()->findOrFail((int) $category->id);
    $manuallyArchivedItem = MenuItem::withTrashed()->findOrFail((int) $manuallyArchivedItem->id);
    $cascadeArchivedItem = MenuItem::query()->findOrFail((int) $cascadeArchivedItem->id);

    expect($category->trashed())->toBeFalse()
        ->and($manuallyArchivedItem->trashed())->toBeTrue()
        ->and($cascadeArchivedItem->trashed())->toBeFalse()
        ->and($cascadeArchivedItem->archived_with_category_id)->toBeNull()
        ->and(app(ListMenuItems::class)()->pluck('id')->all())->toBe([(int) $cascadeArchivedItem->id]);

    app(RestoreMenuItem::class)((int) $manuallyArchivedItem->id);

    expect(MenuItem::query()->pluck('id')->all())->toBe([
        (int) $manuallyArchivedItem->id,
        (int) $cascadeArchivedItem->id,
    ]);
});

it('requires a resolved branch context for branch-owned item actions', function (): void {
    $tenant = menuActionsTenant('tenant-a', 'Tenant A');

    app(TenantResolver::class)->set((int) $tenant['tenant']->id);

    $category = MenuCategory::query()->create([
        'translated_name' => menuActionsText('Breakfast')->toArray(),
        'active' => true,
    ]);

    app(BranchContext::class)->clear();

    expect(fn () => app(CreateMenuItem::class)(
        (int) $category->id,
        menuActionsText('Omelette'),
        null,
        new Money(180000, 'AMD'),
    ))->toThrow(MenuDomainException::class, 'Menu item operations require a resolved branch context.');
});

it('does not update or delete menu items outside the current branch context', function (): void {
    $tenant = menuActionsTenant('tenant-a', 'Tenant A');

    app(TenantResolver::class)->set((int) $tenant['tenant']->id);
    app(BranchContext::class)->set((int) $tenant['branch']->id);

    $otherBranch = Branch::query()->create([
        'name' => 'Other Branch',
        'timezone' => 'Asia/Yerevan',
        'status' => 'active',
    ]);

    $category = app(CreateMenuCategory::class)(menuActionsText('Breakfast'));
    $item = app(CreateMenuItem::class)(
        (int) $category->id,
        menuActionsText('Omelette'),
        null,
        new Money(180000, 'AMD'),
    );

    app(BranchContext::class)->set((int) $otherBranch->id);

    expect(fn () => app(UpdateMenuItem::class)(
        (int) $item->id,
        (int) $category->id,
        menuActionsText('Compromised'),
        null,
        new Money(1, 'AMD'),
        sortOrder: 0,
        active: true,
    ))->toThrow(ModelNotFoundException::class)
        ->and(fn () => app(ArchiveMenuItem::class)((int) $item->id))
        ->toThrow(ModelNotFoundException::class);
});

it('returns 403 through identity authorizer for users without menu permissions', function (): void {
    $allowed = menuActionsUser('tenant-a', 'manager-a', ['menu.items.manage']);
    $denied = menuActionsUser('tenant-b', 'waiter-b', []);

    Route::middleware(['web', 'auth', 'can:menu.items.manage'])
        ->get('/_test/menu-permission', fn () => response('ok'));

    $this->actingAs($denied['user'])
        ->get('/_test/menu-permission')
        ->assertForbidden();

    $this->actingAs($allowed['user'])
        ->withSession(['branch_id' => (int) $allowed['branch']->id])
        ->get('/_test/menu-permission')
        ->assertOk();
});

/**
 * @return array{tenant: Tenant, branch: Branch}
 */
function menuActionsTenant(string $slug, string $name): array
{
    $tenant = Tenant::query()->create([
        'name' => $name,
        'slug' => $slug,
        'default_locale' => 'hy',
        'currency' => 'AMD',
        'status' => 'active',
    ]);

    app(TenantResolver::class)->set((int) $tenant->id);

    $branch = Branch::query()->create([
        'name' => "{$name} Branch",
        'timezone' => 'Asia/Yerevan',
        'status' => 'active',
    ]);

    app(BranchContext::class)->set((int) $branch->id);

    return [
        'tenant' => $tenant,
        'branch' => $branch,
    ];
}

/**
 * @param  list<string>  $permissionCodes
 * @return array{tenant: Tenant, branch: Branch, user: User}
 */
function menuActionsUser(string $tenantSlug, string $username, array $permissionCodes): array
{
    $record = menuActionsTenant($tenantSlug, str($tenantSlug)->headline()->toString());
    $tenantId = (int) $record['tenant']->id;

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
        ['tenant_id' => $tenantId],
    );

    $user = User::query()->create([
        'role_id' => (int) $role->id,
        'name' => $username,
        'email' => "{$username}@smartrest.test",
        'username' => $username,
        'default_locale' => 'hy',
        'active' => true,
        'password' => Hash::make('password'),
    ]);

    UserBranchAssignment::query()->create([
        'user_id' => (int) $user->id,
        'branch_id' => (int) $record['branch']->id,
    ]);

    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();

    return [
        'tenant' => $record['tenant'],
        'branch' => $record['branch'],
        'user' => $user,
    ];
}

function menuActionsText(string $text): LocalizedText
{
    return LocalizedText::fromArray([
        'hy' => $text,
        'ru' => $text,
        'en' => $text,
    ]);
}
