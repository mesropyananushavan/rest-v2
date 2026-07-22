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
use App\Modules\Menu\Application\ForceDeleteMenuCategory;
use App\Modules\Menu\Application\ForceDeleteMenuItem;
use App\Modules\Menu\Application\ListMenuCategories;
use App\Modules\Menu\Application\ListMenuItems;
use App\Modules\Menu\Application\RemoveMenuItemImage;
use App\Modules\Menu\Application\ReplaceMenuItemImage;
use App\Modules\Menu\Application\RestoreMenuCategory;
use App\Modules\Menu\Application\RestoreMenuItem;
use App\Modules\Menu\Application\UpdateMenuCategory;
use App\Modules\Menu\Application\UpdateMenuItem;
use App\Modules\Menu\Domain\MenuDomainException;
use App\Modules\Menu\Domain\MenuItemImageSlot;
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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

afterEach(function (): void {
    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();
});

it('creates updates lists archives and restores menu categories and items through application actions', function (): void {
    $tenant = menuActionsTenant('tenant-a', 'Tenant A');

    app(TenantResolver::class)->set((int) $tenant['tenant']->id);
    app(BranchContext::class)->set((int) $tenant['branch']->id);

    $root = app(CreateMenuCategory::class)(
        menuActionsText('Menu'),
        sortOrder: 1,
        active: true,
    );
    $category = app(CreateMenuCategory::class)(
        menuActionsText('Breakfast'),
        sortOrder: 5,
        active: true,
        parentId: (int) $root->id,
    );

    expect(app(ListMenuCategories::class)()->pluck('id')->all())->toBe([(int) $root->id, (int) $category->id]);

    $updatedCategory = app(UpdateMenuCategory::class)(
        (int) $category->id,
        menuActionsText('Morning menu'),
        sortOrder: 7,
        active: false,
        parentId: (int) $root->id,
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
        ->and(app(ListMenuCategories::class)()->pluck('id')->all())->toBe([(int) $root->id])
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

it('enforces category depth and blocks cross-tenant category parents', function (): void {
    $tenantA = menuActionsTenant('tenant-a', 'Tenant A');
    $tenantB = menuActionsTenant('tenant-b', 'Tenant B');

    app(TenantResolver::class)->set((int) $tenantB['tenant']->id);
    $foreignRoot = app(CreateMenuCategory::class)(menuActionsText('Foreign Root'));

    app(TenantResolver::class)->set((int) $tenantA['tenant']->id);
    $root = app(CreateMenuCategory::class)(menuActionsText('Root'));
    $subcategory = app(CreateMenuCategory::class)(menuActionsText('Subcategory'), parentId: (int) $root->id);

    expect(fn () => app(CreateMenuCategory::class)(menuActionsText('Invalid child'), parentId: (int) $subcategory->id))
        ->toThrow(MenuDomainException::class, 'Menu subcategories must belong to a root category in the current tenant.')
        ->and(fn () => app(CreateMenuCategory::class)(menuActionsText('Foreign child'), parentId: (int) $foreignRoot->id))
        ->toThrow(MenuDomainException::class, 'Menu subcategories must belong to a root category in the current tenant.')
        ->and(MenuCategory::query()->pluck('translated_name')->pluck('en')->all())->toBe(['Root', 'Subcategory']);
});

it('rejects self-parent category updates before database check constraints run', function (): void {
    $tenant = menuActionsTenant('tenant-a', 'Tenant A');

    app(TenantResolver::class)->set((int) $tenant['tenant']->id);

    $category = app(CreateMenuCategory::class)(menuActionsText('Root'));

    expect(fn () => app(UpdateMenuCategory::class)(
        (int) $category->id,
        menuActionsText('Self Parent'),
        sortOrder: 0,
        active: true,
        parentId: (int) $category->id,
    ))->toThrow(MenuDomainException::class, 'Menu subcategories must belong to a root category in the current tenant.');

    expect(MenuCategory::query()->findOrFail((int) $category->id)->parent_id)->toBeNull();
});

it('requires menu items to belong to tenant-scoped subcategories', function (): void {
    $tenantA = menuActionsTenant('tenant-a', 'Tenant A');
    $tenantB = menuActionsTenant('tenant-b', 'Tenant B');

    app(TenantResolver::class)->set((int) $tenantB['tenant']->id);
    app(BranchContext::class)->set((int) $tenantB['branch']->id);
    $foreignRoot = app(CreateMenuCategory::class)(menuActionsText('Foreign Root'));
    $foreignSubcategory = app(CreateMenuCategory::class)(menuActionsText('Foreign Subcategory'), parentId: (int) $foreignRoot->id);

    app(TenantResolver::class)->set((int) $tenantA['tenant']->id);
    app(BranchContext::class)->set((int) $tenantA['branch']->id);
    $root = app(CreateMenuCategory::class)(menuActionsText('Root'));
    $subcategory = app(CreateMenuCategory::class)(menuActionsText('Subcategory'), parentId: (int) $root->id);

    expect(fn () => app(CreateMenuItem::class)(
        (int) $root->id,
        menuActionsText('Root Item'),
        null,
        new Money(100000, 'AMD'),
        ))->toThrow(MenuDomainException::class, 'Menu items must belong to a subcategory.')
        ->and(fn () => app(CreateMenuItem::class)(
            (int) $foreignSubcategory->id,
            menuActionsText('Foreign Item'),
            null,
            new Money(100000, 'AMD'),
        ))->toThrow(ModelNotFoundException::class)
        ->and(MenuItem::query()->count())->toBe(0);

    $item = app(CreateMenuItem::class)(
        (int) $subcategory->id,
        menuActionsText('Valid Item'),
        null,
        new Money(100000, 'AMD'),
    );

    expect((int) $item->category_id)->toBe((int) $subcategory->id);
});

it('blocks parent changes for categories with subcategories or items', function (): void {
    $tenant = menuActionsTenant('tenant-a', 'Tenant A');

    app(TenantResolver::class)->set((int) $tenant['tenant']->id);
    app(BranchContext::class)->set((int) $tenant['branch']->id);

    $root = app(CreateMenuCategory::class)(menuActionsText('Root'));
    $otherRoot = app(CreateMenuCategory::class)(menuActionsText('Other Root'));
    $subcategory = app(CreateMenuCategory::class)(menuActionsText('Subcategory'), parentId: (int) $root->id);
    $filledSubcategory = app(CreateMenuCategory::class)(menuActionsText('Filled Subcategory'), parentId: (int) $root->id);
    app(CreateMenuItem::class)(
        (int) $filledSubcategory->id,
        menuActionsText('Filled Item'),
        null,
        new Money(100000, 'AMD'),
    );

    expect(fn () => app(UpdateMenuCategory::class)(
        (int) $root->id,
        menuActionsText('Moved Root'),
        sortOrder: 0,
        active: true,
        parentId: (int) $otherRoot->id,
    ))->toThrow(MenuDomainException::class, 'Menu categories with subcategories or items cannot be moved.')
        ->and(fn () => app(UpdateMenuCategory::class)(
            (int) $filledSubcategory->id,
            menuActionsText('Moved Subcategory'),
            sortOrder: 0,
            active: true,
            parentId: (int) $otherRoot->id,
        ))->toThrow(MenuDomainException::class, 'Menu categories with subcategories or items cannot be moved.');

    $movedEmptySubcategory = app(UpdateMenuCategory::class)(
        (int) $subcategory->id,
        menuActionsText('Moved Empty Subcategory'),
        sortOrder: 0,
        active: true,
        parentId: (int) $otherRoot->id,
    );

    expect((int) $movedEmptySubcategory->parent_id)->toBe((int) $otherRoot->id);
});

it('root category archive and restore only cascades descendants marked by that root', function (): void {
    $tenant = menuActionsTenant('tenant-a', 'Tenant A');

    app(TenantResolver::class)->set((int) $tenant['tenant']->id);
    app(BranchContext::class)->set((int) $tenant['branch']->id);

    $root = app(CreateMenuCategory::class)(menuActionsText('Menu'));
    $breakfast = app(CreateMenuCategory::class)(menuActionsText('Breakfast'), parentId: (int) $root->id);
    $lunch = app(CreateMenuCategory::class)(menuActionsText('Lunch'), parentId: (int) $root->id);
    $independent = app(CreateMenuCategory::class)(menuActionsText('Archived before root'), parentId: (int) $root->id);

    $omelette = app(CreateMenuItem::class)(
        (int) $breakfast->id,
        menuActionsText('Omelette'),
        null,
        new Money(180000, 'AMD'),
    );
    $toast = app(CreateMenuItem::class)(
        (int) $breakfast->id,
        menuActionsText('Toast'),
        null,
        new Money(90000, 'AMD'),
    );
    $salad = app(CreateMenuItem::class)(
        (int) $lunch->id,
        menuActionsText('Salad'),
        null,
        new Money(120000, 'AMD'),
    );
    $independentItem = app(CreateMenuItem::class)(
        (int) $independent->id,
        menuActionsText('Already archived item'),
        null,
        new Money(110000, 'AMD'),
    );

    app(ArchiveMenuItem::class)((int) $toast->id);
    app(ArchiveMenuCategory::class)((int) $independent->id);
    app(ArchiveMenuCategory::class)((int) $root->id);

    $root = MenuCategory::withTrashed()->findOrFail((int) $root->id);
    $breakfast = MenuCategory::withTrashed()->findOrFail((int) $breakfast->id);
    $lunch = MenuCategory::withTrashed()->findOrFail((int) $lunch->id);
    $independent = MenuCategory::withTrashed()->findOrFail((int) $independent->id);
    $omelette = MenuItem::withTrashed()->findOrFail((int) $omelette->id);
    $toast = MenuItem::withTrashed()->findOrFail((int) $toast->id);
    $salad = MenuItem::withTrashed()->findOrFail((int) $salad->id);
    $independentItem = MenuItem::withTrashed()->findOrFail((int) $independentItem->id);

    expect($root->trashed())->toBeTrue()
        ->and($breakfast->trashed())->toBeTrue()
        ->and($breakfast->archived_with_category_id)->toBe((int) $root->id)
        ->and($lunch->trashed())->toBeTrue()
        ->and($lunch->archived_with_category_id)->toBe((int) $root->id)
        ->and($omelette->trashed())->toBeTrue()
        ->and($omelette->archived_with_category_id)->toBe((int) $root->id)
        ->and($salad->trashed())->toBeTrue()
        ->and($salad->archived_with_category_id)->toBe((int) $root->id)
        ->and($toast->trashed())->toBeTrue()
        ->and($toast->archived_with_category_id)->toBeNull()
        ->and($independent->trashed())->toBeTrue()
        ->and($independent->archived_with_category_id)->toBeNull()
        ->and($independentItem->trashed())->toBeTrue()
        ->and($independentItem->archived_with_category_id)->toBe((int) $independent->id);

    app(RestoreMenuCategory::class)((int) $root->id);

    $root = MenuCategory::query()->findOrFail((int) $root->id);
    $breakfast = MenuCategory::query()->findOrFail((int) $breakfast->id);
    $lunch = MenuCategory::query()->findOrFail((int) $lunch->id);
    $independent = MenuCategory::withTrashed()->findOrFail((int) $independent->id);
    $omelette = MenuItem::query()->findOrFail((int) $omelette->id);
    $salad = MenuItem::query()->findOrFail((int) $salad->id);
    $toast = MenuItem::withTrashed()->findOrFail((int) $toast->id);
    $independentItem = MenuItem::withTrashed()->findOrFail((int) $independentItem->id);

    expect($root->trashed())->toBeFalse()
        ->and($breakfast->trashed())->toBeFalse()
        ->and($breakfast->archived_with_category_id)->toBeNull()
        ->and($lunch->trashed())->toBeFalse()
        ->and($lunch->archived_with_category_id)->toBeNull()
        ->and($omelette->trashed())->toBeFalse()
        ->and($omelette->archived_with_category_id)->toBeNull()
        ->and($salad->trashed())->toBeFalse()
        ->and($salad->archived_with_category_id)->toBeNull()
        ->and($toast->trashed())->toBeTrue()
        ->and($toast->archived_with_category_id)->toBeNull()
        ->and($independent->trashed())->toBeTrue()
        ->and($independent->archived_with_category_id)->toBeNull()
        ->and($independentItem->trashed())->toBeTrue()
        ->and($independentItem->archived_with_category_id)->toBe((int) $independent->id);
});

it('subcategory archive and restore only cascades its own item marker', function (): void {
    $tenant = menuActionsTenant('tenant-a', 'Tenant A');

    app(TenantResolver::class)->set((int) $tenant['tenant']->id);
    app(BranchContext::class)->set((int) $tenant['branch']->id);

    $root = app(CreateMenuCategory::class)(menuActionsText('Menu'));
    $breakfast = app(CreateMenuCategory::class)(menuActionsText('Breakfast'), parentId: (int) $root->id);
    $lunch = app(CreateMenuCategory::class)(menuActionsText('Lunch'), parentId: (int) $root->id);
    $omelette = app(CreateMenuItem::class)(
        (int) $breakfast->id,
        menuActionsText('Omelette'),
        null,
        new Money(180000, 'AMD'),
    );
    $salad = app(CreateMenuItem::class)(
        (int) $lunch->id,
        menuActionsText('Salad'),
        null,
        new Money(120000, 'AMD'),
    );

    app(ArchiveMenuCategory::class)((int) $breakfast->id);

    $breakfast = MenuCategory::withTrashed()->findOrFail((int) $breakfast->id);
    $lunch = MenuCategory::query()->findOrFail((int) $lunch->id);
    $omelette = MenuItem::withTrashed()->findOrFail((int) $omelette->id);
    $salad = MenuItem::query()->findOrFail((int) $salad->id);

    expect($breakfast->trashed())->toBeTrue()
        ->and($breakfast->archived_with_category_id)->toBeNull()
        ->and($lunch->trashed())->toBeFalse()
        ->and($omelette->trashed())->toBeTrue()
        ->and($omelette->archived_with_category_id)->toBe((int) $breakfast->id)
        ->and($salad->trashed())->toBeFalse()
        ->and($salad->archived_with_category_id)->toBeNull();

    app(RestoreMenuCategory::class)((int) $breakfast->id);

    $breakfast = MenuCategory::query()->findOrFail((int) $breakfast->id);
    $omelette = MenuItem::query()->findOrFail((int) $omelette->id);
    $salad = MenuItem::query()->findOrFail((int) $salad->id);

    expect($breakfast->trashed())->toBeFalse()
        ->and($omelette->trashed())->toBeFalse()
        ->and($omelette->archived_with_category_id)->toBeNull()
        ->and($salad->trashed())->toBeFalse()
        ->and($salad->archived_with_category_id)->toBeNull();
});

it('blocks restoring a subcategory while its root category is archived', function (): void {
    $tenant = menuActionsTenant('tenant-a', 'Tenant A');

    app(TenantResolver::class)->set((int) $tenant['tenant']->id);
    app(BranchContext::class)->set((int) $tenant['branch']->id);

    $root = app(CreateMenuCategory::class)(menuActionsText('Menu'));
    $subcategory = app(CreateMenuCategory::class)(menuActionsText('Breakfast'), parentId: (int) $root->id);

    app(ArchiveMenuCategory::class)((int) $root->id);

    expect(fn () => app(RestoreMenuCategory::class)((int) $subcategory->id))
        ->toThrow(MenuDomainException::class, 'Restore the parent category before restoring this subcategory.');

    $subcategory = MenuCategory::withTrashed()->findOrFail((int) $subcategory->id);

    expect($subcategory->trashed())->toBeTrue()
        ->and($subcategory->archived_with_category_id)->toBe((int) $root->id);
});

it('force deletes a root category subtree and removes all descendant item images', function (): void {
    Storage::fake('public');

    $tenant = menuActionsTenant('tenant-a', 'Tenant A');

    app(TenantResolver::class)->set((int) $tenant['tenant']->id);
    app(BranchContext::class)->set((int) $tenant['branch']->id);

    $root = app(CreateMenuCategory::class)(menuActionsText('Menu'));
    $breakfast = app(CreateMenuCategory::class)(menuActionsText('Breakfast'), parentId: (int) $root->id);
    $lunch = app(CreateMenuCategory::class)(menuActionsText('Lunch'), parentId: (int) $root->id);

    $rootMarkedItem = app(CreateMenuItem::class)(
        (int) $breakfast->id,
        menuActionsText('Omelette'),
        null,
        new Money(180000, 'AMD'),
    );
    $independentlyArchivedItem = app(CreateMenuItem::class)(
        (int) $lunch->id,
        menuActionsText('Salad'),
        null,
        new Money(120000, 'AMD'),
    );

    $rootMarkedItem = app(ReplaceMenuItemImage::class)(
        (int) $rootMarkedItem->id,
        MenuItemImageSlot::Internal,
        UploadedFile::fake()->image('omelette.jpg', 500, 400)->size(128),
    );
    $independentlyArchivedItem = app(ReplaceMenuItemImage::class)(
        (int) $independentlyArchivedItem->id,
        MenuItemImageSlot::Public,
        UploadedFile::fake()->image('salad.jpg', 500, 400)->size(128),
    );
    $rootMarkedImage = menuActionImageMetadata($rootMarkedItem, 'internal_image');
    $independentlyArchivedImage = menuActionImageMetadata($independentlyArchivedItem, 'public_image');

    app(ArchiveMenuItem::class)((int) $independentlyArchivedItem->id);
    app(ArchiveMenuCategory::class)((int) $root->id);
    app(ForceDeleteMenuCategory::class)((int) $root->id);

    expect(MenuCategory::withTrashed()->find((int) $root->id))->toBeNull()
        ->and(MenuCategory::withTrashed()->find((int) $breakfast->id))->toBeNull()
        ->and(MenuCategory::withTrashed()->find((int) $lunch->id))->toBeNull()
        ->and(MenuItem::withTrashed()->find((int) $rootMarkedItem->id))->toBeNull()
        ->and(MenuItem::withTrashed()->find((int) $independentlyArchivedItem->id))->toBeNull();

    Storage::disk('public')->assertMissing($rootMarkedImage['path']);
    Storage::disk('public')->assertMissing($rootMarkedImage['thumbnail_path']);
    Storage::disk('public')->assertMissing($independentlyArchivedImage['path']);
    Storage::disk('public')->assertMissing($independentlyArchivedImage['thumbnail_path']);
});

it('force deletes independently archived subcategories inside a root subtree', function (): void {
    Storage::fake('public');

    $tenant = menuActionsTenant('tenant-a', 'Tenant A');

    app(TenantResolver::class)->set((int) $tenant['tenant']->id);
    app(BranchContext::class)->set((int) $tenant['branch']->id);

    $root = app(CreateMenuCategory::class)(menuActionsText('Menu'));
    $breakfast = app(CreateMenuCategory::class)(menuActionsText('Breakfast'), parentId: (int) $root->id);
    $archivedBeforeRoot = app(CreateMenuCategory::class)(menuActionsText('Archived before root'), parentId: (int) $root->id);

    $rootMarkedItem = app(CreateMenuItem::class)(
        (int) $breakfast->id,
        menuActionsText('Omelette'),
        null,
        new Money(180000, 'AMD'),
    );
    $independentItem = app(CreateMenuItem::class)(
        (int) $archivedBeforeRoot->id,
        menuActionsText('Archived category item'),
        null,
        new Money(120000, 'AMD'),
    );
    $independentItem = app(ReplaceMenuItemImage::class)(
        (int) $independentItem->id,
        MenuItemImageSlot::Internal,
        UploadedFile::fake()->image('archived-category-item.jpg', 500, 400)->size(128),
    );
    $independentImage = menuActionImageMetadata($independentItem, 'internal_image');

    app(ArchiveMenuCategory::class)((int) $archivedBeforeRoot->id);

    $archivedBeforeRoot = MenuCategory::withTrashed()->findOrFail((int) $archivedBeforeRoot->id);
    $independentItem = MenuItem::withTrashed()->findOrFail((int) $independentItem->id);

    expect($archivedBeforeRoot->trashed())->toBeTrue()
        ->and($archivedBeforeRoot->archived_with_category_id)->toBeNull()
        ->and($independentItem->trashed())->toBeTrue()
        ->and($independentItem->archived_with_category_id)->toBe((int) $archivedBeforeRoot->id);

    app(ArchiveMenuCategory::class)((int) $root->id);
    app(ForceDeleteMenuCategory::class)((int) $root->id);

    expect(MenuCategory::withTrashed()->find((int) $root->id))->toBeNull()
        ->and(MenuCategory::withTrashed()->find((int) $breakfast->id))->toBeNull()
        ->and(MenuCategory::withTrashed()->find((int) $archivedBeforeRoot->id))->toBeNull()
        ->and(MenuItem::withTrashed()->find((int) $rootMarkedItem->id))->toBeNull()
        ->and(MenuItem::withTrashed()->find((int) $independentItem->id))->toBeNull();

    Storage::disk('public')->assertMissing($independentImage['path']);
    Storage::disk('public')->assertMissing($independentImage['thumbnail_path']);
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

    $root = app(CreateMenuCategory::class)(menuActionsText('Menu'));
    $category = app(CreateMenuCategory::class)(menuActionsText('Breakfast'), parentId: (int) $root->id);
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

it('replaces and removes internal and public menu item images through tenant-scoped storage', function (): void {
    Storage::fake('public');

    $tenant = menuActionsTenant('tenant-a', 'Tenant A');

    app(TenantResolver::class)->set((int) $tenant['tenant']->id);
    app(BranchContext::class)->set((int) $tenant['branch']->id);

    $root = app(CreateMenuCategory::class)(menuActionsText('Menu'));
    $category = app(CreateMenuCategory::class)(menuActionsText('Breakfast'), parentId: (int) $root->id);
    $item = app(CreateMenuItem::class)(
        (int) $category->id,
        menuActionsText('Omelette'),
        null,
        new Money(180000, 'AMD'),
    );

    $item = app(ReplaceMenuItemImage::class)(
        (int) $item->id,
        MenuItemImageSlot::Internal,
        UploadedFile::fake()->image('internal.jpg', 900, 700)->size(128),
    );
    $firstInternalImage = menuActionImageMetadata($item, 'internal_image');

    expect($firstInternalImage['path'])->toContain("tenants/{$tenant['tenant']->id}/menu/items/{$item->id}/internal/")
        ->and($firstInternalImage['thumbnail_path'])->toContain("tenants/{$tenant['tenant']->id}/menu/items/{$item->id}/internal/")
        ->and($firstInternalImage['mime_type'])->toBe('image/jpeg')
        ->and($firstInternalImage['width'])->toBe(900)
        ->and($firstInternalImage['height'])->toBe(700);
    Storage::disk('public')->assertExists($firstInternalImage['path']);
    Storage::disk('public')->assertExists($firstInternalImage['thumbnail_path']);

    $item = app(ReplaceMenuItemImage::class)(
        (int) $item->id,
        MenuItemImageSlot::Internal,
        UploadedFile::fake()->image('internal-replacement.png', 600, 500)->size(128),
    );
    $secondInternalImage = menuActionImageMetadata($item, 'internal_image');

    Storage::disk('public')->assertMissing($firstInternalImage['path']);
    Storage::disk('public')->assertMissing($firstInternalImage['thumbnail_path']);
    Storage::disk('public')->assertExists($secondInternalImage['path']);
    Storage::disk('public')->assertExists($secondInternalImage['thumbnail_path']);

    $item = app(ReplaceMenuItemImage::class)(
        (int) $item->id,
        MenuItemImageSlot::Public,
        UploadedFile::fake()->image('public.png', 500, 400)->size(128),
    );
    $publicImage = menuActionImageMetadata($item, 'public_image');

    expect($publicImage['path'])->toContain("tenants/{$tenant['tenant']->id}/menu/items/{$item->id}/public/");
    Storage::disk('public')->assertExists($publicImage['path']);
    Storage::disk('public')->assertExists($publicImage['thumbnail_path']);

    $item = app(RemoveMenuItemImage::class)((int) $item->id, MenuItemImageSlot::Internal);

    Storage::disk('public')->assertMissing($secondInternalImage['path']);
    Storage::disk('public')->assertMissing($secondInternalImage['thumbnail_path']);
    expect($item->internal_image)->toBeNull()
        ->and($item->public_image)->not->toBeNull();

    $item = app(RemoveMenuItemImage::class)((int) $item->id, MenuItemImageSlot::Public);

    Storage::disk('public')->assertMissing($publicImage['path']);
    Storage::disk('public')->assertMissing($publicImage['thumbnail_path']);
    expect($item->public_image)->toBeNull();
});

it('rejects unsupported menu item image type and size before storing files', function (): void {
    Storage::fake('public');

    $tenant = menuActionsTenant('tenant-a', 'Tenant A');

    app(TenantResolver::class)->set((int) $tenant['tenant']->id);
    app(BranchContext::class)->set((int) $tenant['branch']->id);

    $root = app(CreateMenuCategory::class)(menuActionsText('Menu'));
    $category = app(CreateMenuCategory::class)(menuActionsText('Breakfast'), parentId: (int) $root->id);
    $item = app(CreateMenuItem::class)(
        (int) $category->id,
        menuActionsText('Omelette'),
        null,
        new Money(180000, 'AMD'),
    );

    expect(fn () => app(ReplaceMenuItemImage::class)(
        (int) $item->id,
        MenuItemImageSlot::Internal,
        UploadedFile::fake()->create('bad.gif', 10, 'image/gif'),
    ))->toThrow(InvalidArgumentException::class, 'Uploaded menu item image type is not supported.')
        ->and(fn () => app(ReplaceMenuItemImage::class)(
            (int) $item->id,
            MenuItemImageSlot::Internal,
            UploadedFile::fake()->image('too-large.jpg', 100, 100)->size(4097),
        ))->toThrow(InvalidArgumentException::class, 'Uploaded menu item image is too large.');

    Storage::disk('public')->assertDirectoryEmpty('/');
});

it('does not allow one tenant branch context to replace or remove another tenant item image', function (): void {
    Storage::fake('public');

    $tenantA = menuActionsTenant('tenant-a', 'Tenant A');
    $tenantB = menuActionsTenant('tenant-b', 'Tenant B');

    app(TenantResolver::class)->set((int) $tenantB['tenant']->id);
    app(BranchContext::class)->set((int) $tenantB['branch']->id);

    $rootB = app(CreateMenuCategory::class)(menuActionsText('Tenant B Menu'));
    $categoryB = app(CreateMenuCategory::class)(menuActionsText('Tenant B Breakfast'), parentId: (int) $rootB->id);
    $itemB = app(CreateMenuItem::class)(
        (int) $categoryB->id,
        menuActionsText('Tenant B Omelette'),
        null,
        new Money(180000, 'AMD'),
    );
    $itemB = app(ReplaceMenuItemImage::class)(
        (int) $itemB->id,
        MenuItemImageSlot::Internal,
        UploadedFile::fake()->image('tenant-b.jpg', 400, 300)->size(128),
    );
    $tenantBImage = menuActionImageMetadata($itemB, 'internal_image');

    app(TenantResolver::class)->set((int) $tenantA['tenant']->id);
    app(BranchContext::class)->set((int) $tenantA['branch']->id);

    expect(fn () => app(ReplaceMenuItemImage::class)(
        (int) $itemB->id,
        MenuItemImageSlot::Internal,
        UploadedFile::fake()->image('compromised.jpg', 400, 300)->size(128),
    ))->toThrow(ModelNotFoundException::class)
        ->and(fn () => app(RemoveMenuItemImage::class)((int) $itemB->id, MenuItemImageSlot::Internal))
        ->toThrow(ModelNotFoundException::class);

    Storage::disk('public')->assertExists($tenantBImage['path']);
    Storage::disk('public')->assertExists($tenantBImage['thumbnail_path']);

    app(TenantResolver::class)->set((int) $tenantB['tenant']->id);

    $itemB = MenuItem::query()->findOrFail((int) $itemB->id);

    expect(menuActionImageMetadata($itemB, 'internal_image')['path'])->toBe($tenantBImage['path']);
});

it('deletes menu item image files during item and category force delete but not archive', function (): void {
    Storage::fake('public');

    $tenant = menuActionsTenant('tenant-a', 'Tenant A');

    app(TenantResolver::class)->set((int) $tenant['tenant']->id);
    app(BranchContext::class)->set((int) $tenant['branch']->id);

    $root = app(CreateMenuCategory::class)(menuActionsText('Menu'));
    $category = app(CreateMenuCategory::class)(menuActionsText('Breakfast'), parentId: (int) $root->id);
    $item = app(CreateMenuItem::class)(
        (int) $category->id,
        menuActionsText('Omelette'),
        null,
        new Money(180000, 'AMD'),
    );
    $item = app(ReplaceMenuItemImage::class)(
        (int) $item->id,
        MenuItemImageSlot::Internal,
        UploadedFile::fake()->image('internal.jpg', 500, 400)->size(128),
    );
    $item = app(ReplaceMenuItemImage::class)(
        (int) $item->id,
        MenuItemImageSlot::Public,
        UploadedFile::fake()->image('public.jpg', 500, 400)->size(128),
    );
    $internalImage = menuActionImageMetadata($item, 'internal_image');
    $publicImage = menuActionImageMetadata($item, 'public_image');

    app(ArchiveMenuItem::class)((int) $item->id);

    Storage::disk('public')->assertExists($internalImage['path']);
    Storage::disk('public')->assertExists($internalImage['thumbnail_path']);
    Storage::disk('public')->assertExists($publicImage['path']);
    Storage::disk('public')->assertExists($publicImage['thumbnail_path']);

    app(ForceDeleteMenuItem::class)((int) $item->id);

    Storage::disk('public')->assertMissing($internalImage['path']);
    Storage::disk('public')->assertMissing($internalImage['thumbnail_path']);
    Storage::disk('public')->assertMissing($publicImage['path']);
    Storage::disk('public')->assertMissing($publicImage['thumbnail_path']);

    $categoryItem = app(CreateMenuItem::class)(
        (int) $category->id,
        menuActionsText('Toast'),
        null,
        new Money(90000, 'AMD'),
    );
    $categoryItem = app(ReplaceMenuItemImage::class)(
        (int) $categoryItem->id,
        MenuItemImageSlot::Internal,
        UploadedFile::fake()->image('category-child.jpg', 500, 400)->size(128),
    );
    $categoryChildImage = menuActionImageMetadata($categoryItem, 'internal_image');

    app(ArchiveMenuCategory::class)((int) $category->id);
    app(ForceDeleteMenuCategory::class)((int) $category->id);

    Storage::disk('public')->assertMissing($categoryChildImage['path']);
    Storage::disk('public')->assertMissing($categoryChildImage['thumbnail_path']);
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

/**
 * @return array{path: string, thumbnail_path: string, mime_type: string, width: int, height: int, size: int}
 */
function menuActionImageMetadata(MenuItem $item, string $column): array
{
    $metadata = $item->getAttribute($column);

    expect($metadata)->toBeArray();

    /** @var array{path: string, thumbnail_path: string, mime_type: string, width: int, height: int, size: int} $metadata */
    return $metadata;
}
