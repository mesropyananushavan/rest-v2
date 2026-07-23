<?php

declare(strict_types=1);

use App\Modules\Menu\Application\ArchiveMenuCategory;
use App\Modules\Menu\Application\ArchiveMenuItem;
use App\Modules\Menu\Application\CreateMenuCategory;
use App\Modules\Menu\Application\CreateMenuItem;
use App\Modules\Menu\Application\PaginateMenuCategories;
use App\Modules\Menu\Application\PaginateMenuItems;
use App\Modules\Menu\Application\ResolveMenuCategorySelection;
use App\Modules\Menu\Application\SearchMenuItems;
use App\Modules\Menu\Domain\MenuDomainException;
use App\Modules\Menu\Infrastructure\Models\MenuCategory;
use App\Modules\Menu\Infrastructure\Models\MenuItem;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Modules\Tenancy\Infrastructure\Models\Branch;
use App\Modules\Tenancy\Infrastructure\Models\Tenant;
use App\Support\I18n\LocalizedText;
use App\Support\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

afterEach(function (): void {
    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();
});

it('paginates root category panel results and searches root and child localized names', function (): void {
    $records = menuQueryTenant('tenant-a', 'Tenant A');

    app(TenantResolver::class)->set((int) $records['tenant']->id);
    app(BranchContext::class)->set((int) $records['branch']->id);

    for ($index = 1; $index <= 30; $index++) {
        $root = app(CreateMenuCategory::class)(
            menuQueryText("Root {$index}", "Արմատ {$index}", "Корень {$index}"),
            sortOrder: $index,
        );

        if ($index !== 30) {
            app(CreateMenuCategory::class)(
                menuQueryText("Category {$index}", "Բաժին {$index}", "Категория {$index}"),
                sortOrder: $index,
                parentId: (int) $root->id,
            );
        }
    }

    $firstPage = app(PaginateMenuCategories::class)(perPage: 10, page: 1);
    $secondPage = app(PaginateMenuCategories::class)(perPage: 10, page: 2);
    $armenianRootSearch = app(PaginateMenuCategories::class)(search: 'Արմատ 30', perPage: 25);
    $armenianChildSearch = app(PaginateMenuCategories::class)(search: 'Բաժին 2', perPage: 25);

    expect($firstPage->total())->toBe(30)
        ->and($firstPage->count())->toBe(10)
        ->and($firstPage->items()[0]->translatedName()->forLocale('en'))->toBe('Root 1')
        ->and($firstPage->items()[0]->subcategories)->toHaveCount(1)
        ->and($secondPage->items()[0]->translatedName()->forLocale('en'))->toBe('Root 11')
        ->and($armenianRootSearch->total())->toBe(1)
        ->and($armenianRootSearch->items()[0]->translatedName()->forLocale('en'))->toBe('Root 30')
        ->and($armenianRootSearch->items()[0]->subcategories)->toHaveCount(0)
        ->and($armenianChildSearch->total())->toBe(11)
        ->and(collect($armenianChildSearch->items())->pluck('translated_name')->flatten()->contains('Արմատ 20'))->toBeTrue();
});

it('resolves selected subcategory defaults without relying on root sort order', function (): void {
    $records = menuQueryTenant('tenant-a', 'Tenant A');

    app(TenantResolver::class)->set((int) $records['tenant']->id);
    app(BranchContext::class)->set((int) $records['branch']->id);

    $root = app(CreateMenuCategory::class)(menuQueryText('Menu'), sortOrder: 0);
    $breakfast = app(CreateMenuCategory::class)(menuQueryText('Breakfast'), sortOrder: 10, parentId: (int) $root->id);
    $lunch = app(CreateMenuCategory::class)(menuQueryText('Lunch'), sortOrder: 20, parentId: (int) $root->id);
    $otherRoot = app(CreateMenuCategory::class)(menuQueryText('Other Menu'), sortOrder: 1);
    $dinner = app(CreateMenuCategory::class)(menuQueryText('Dinner'), sortOrder: 5, parentId: (int) $otherRoot->id);
    $emptyRoot = app(CreateMenuCategory::class)(menuQueryText('Empty Menu'), sortOrder: 40);

    $default = app(ResolveMenuCategorySelection::class)(archiveMode: 'active');
    $rootSelection = app(ResolveMenuCategorySelection::class)((int) $root->id, 'active');
    $emptyRootSelection = app(ResolveMenuCategorySelection::class)((int) $emptyRoot->id, 'active');
    $directSelection = app(ResolveMenuCategorySelection::class)((int) $lunch->id, 'active');

    expect($default?->is($breakfast))->toBeTrue()
        ->and($rootSelection?->is($breakfast))->toBeTrue()
        ->and($emptyRootSelection)->toBeNull()
        ->and($directSelection?->is($lunch))->toBeTrue()
        ->and($dinner->parent_id)->toBe((int) $otherRoot->id);
});

it('keeps previous tenant fallback behavior for foreign selected category ids', function (): void {
    $tenantA = menuQueryTenant('tenant-a', 'Tenant A');

    app(TenantResolver::class)->set((int) $tenantA['tenant']->id);
    app(BranchContext::class)->set((int) $tenantA['branch']->id);

    $rootA = app(CreateMenuCategory::class)(menuQueryText('Tenant A Menu'), sortOrder: 1);
    $categoryA = app(CreateMenuCategory::class)(menuQueryText('Tenant A Breakfast'), parentId: (int) $rootA->id);

    $tenantB = menuQueryTenant('tenant-b', 'Tenant B');

    app(TenantResolver::class)->set((int) $tenantB['tenant']->id);
    app(BranchContext::class)->set((int) $tenantB['branch']->id);

    $rootB = app(CreateMenuCategory::class)(menuQueryText('Tenant B Menu'), sortOrder: 1);
    $categoryB = app(CreateMenuCategory::class)(menuQueryText('Tenant B Breakfast'), parentId: (int) $rootB->id);

    app(TenantResolver::class)->set((int) $tenantA['tenant']->id);
    app(BranchContext::class)->set((int) $tenantA['branch']->id);

    $selection = app(ResolveMenuCategorySelection::class)((int) $categoryB->id, 'active');

    expect($selection?->is($categoryA))->toBeTrue();
});

it('paginates selected-category items and applies inactive and archive filters', function (): void {
    $records = menuQueryTenant('tenant-a', 'Tenant A');

    app(TenantResolver::class)->set((int) $records['tenant']->id);
    app(BranchContext::class)->set((int) $records['branch']->id);

    $root = app(CreateMenuCategory::class)(menuQueryText('Menu'));
    $category = app(CreateMenuCategory::class)(menuQueryText('Breakfast'), parentId: (int) $root->id);

    for ($index = 1; $index <= 32; $index++) {
        $item = app(CreateMenuItem::class)(
            (int) $category->id,
            menuQueryText("Dish {$index}"),
            null,
            new Money(100000 + $index, 'AMD'),
            sortOrder: $index,
            active: $index % 5 !== 0,
        );

        if ($index === 7) {
            app(ArchiveMenuItem::class)((int) $item->id);
        }
    }

    $activeOnly = app(PaginateMenuItems::class)((int) $category->id, perPage: 25);
    $withInactive = app(PaginateMenuItems::class)((int) $category->id, includeInactive: true, perPage: 50);
    $archivedOnly = app(PaginateMenuItems::class)((int) $category->id, archiveMode: 'archived', perPage: 50);
    $withArchived = app(PaginateMenuItems::class)((int) $category->id, includeInactive: true, archiveMode: 'all', perPage: 50);

    expect($activeOnly->total())->toBe(25)
        ->and($activeOnly->count())->toBe(25)
        ->and($withInactive->total())->toBe(31)
        ->and($archivedOnly->total())->toBe(1)
        ->and($withArchived->total())->toBe(32)
        ->and(collect($activeOnly->items())->contains(fn (MenuItem $item): bool => (int) $item->sort_order === 5))->toBeFalse()
        ->and(collect($archivedOnly->items())->every(fn (MenuItem $item): bool => $item->trashed()))->toBeTrue()
        ->and(collect($withArchived->items())->contains(fn (MenuItem $item): bool => ! $item->trashed()))->toBeTrue()
        ->and(collect($withArchived->items())->contains(fn (MenuItem $item): bool => $item->trashed()))->toBeTrue();
});

it('shows only archived category containers in archived category panel mode', function (): void {
    $records = menuQueryTenant('tenant-a', 'Tenant A');

    app(TenantResolver::class)->set((int) $records['tenant']->id);
    app(BranchContext::class)->set((int) $records['branch']->id);

    $root = app(CreateMenuCategory::class)(menuQueryText('Menu'), sortOrder: 1);
    $breakfast = app(CreateMenuCategory::class)(menuQueryText('Breakfast'), sortOrder: 10, parentId: (int) $root->id);
    app(CreateMenuCategory::class)(menuQueryText('Dinner'), sortOrder: 20, parentId: (int) $root->id);

    $archivedItem = app(CreateMenuItem::class)(
        (int) $breakfast->id,
        menuQueryText('Archived Omelette'),
        null,
        new Money(100000, 'AMD'),
    );
    app(ArchiveMenuItem::class)((int) $archivedItem->id);

    $archivedPanel = app(PaginateMenuCategories::class)(archiveMode: 'archived', perPage: 25);

    expect($archivedPanel->total())->toBe(1)
        ->and($archivedPanel->items()[0]->translatedName()->forLocale('en'))->toBe('Menu')
        ->and($archivedPanel->items()[0]->subcategories)->toHaveCount(1)
        ->and($archivedPanel->items()[0]->subcategories->first()?->translatedName()->forLocale('en'))->toBe('Breakfast');
});

it('shows archived empty roots, roots with archived children, and both in all category panel mode', function (): void {
    $records = menuQueryTenant('tenant-a', 'Tenant A');

    app(TenantResolver::class)->set((int) $records['tenant']->id);
    app(BranchContext::class)->set((int) $records['branch']->id);

    $archivedEmptyRoot = app(CreateMenuCategory::class)(menuQueryText('Archived Empty Root'), sortOrder: 1);
    $rootWithArchivedChild = app(CreateMenuCategory::class)(menuQueryText('Root With Archived Child'), sortOrder: 2);
    $archivedChild = app(CreateMenuCategory::class)(menuQueryText('Archived Child'), parentId: (int) $rootWithArchivedChild->id);
    $activeRoot = app(CreateMenuCategory::class)(menuQueryText('Active Root'), sortOrder: 3);
    app(CreateMenuCategory::class)(menuQueryText('Active Child'), parentId: (int) $activeRoot->id);

    app(ArchiveMenuCategory::class)((int) $archivedEmptyRoot->id);
    app(ArchiveMenuCategory::class)((int) $archivedChild->id);

    $archivedPanel = app(PaginateMenuCategories::class)(archiveMode: 'archived', perPage: 25);
    $allPanel = app(PaginateMenuCategories::class)(archiveMode: 'all', perPage: 25);

    expect($archivedPanel->total())->toBe(2)
        ->and(collect($archivedPanel->items())->map(fn (MenuCategory $category): string => $category->translatedName()->forLocale('en'))->all())
        ->toBe(['Archived Empty Root', 'Root With Archived Child'])
        ->and($archivedPanel->items()[0]->subcategories)->toHaveCount(0)
        ->and($archivedPanel->items()[1]->subcategories)->toHaveCount(1)
        ->and($allPanel->total())->toBe(3)
        ->and(collect($allPanel->items())->map(fn (MenuCategory $category): string => $category->translatedName()->forLocale('en'))->all())
        ->toBe(['Archived Empty Root', 'Root With Archived Child', 'Active Root']);
});

it('searches menu items across all localized names within the current branch only', function (): void {
    $records = menuQueryTenant('tenant-a', 'Tenant A');

    app(TenantResolver::class)->set((int) $records['tenant']->id);
    app(BranchContext::class)->set((int) $records['branch']->id);

    $root = app(CreateMenuCategory::class)(menuQueryText('Menu'));
    $category = app(CreateMenuCategory::class)(menuQueryText('Breakfast'), parentId: (int) $root->id);
    app(CreateMenuItem::class)((int) $category->id, menuQueryText('Lori Omelette', 'Լոռի ձվածեղ', 'Лорийский омлет'), null, new Money(180000, 'AMD'));
    app(CreateMenuItem::class)((int) $category->id, menuQueryText('Hidden Soup'), null, new Money(90000, 'AMD'), active: false);
    $archivedItem = app(CreateMenuItem::class)((int) $category->id, menuQueryText('Archived Cutlet'), null, new Money(120000, 'AMD'));
    app(ArchiveMenuItem::class)((int) $archivedItem->id);

    $otherBranch = Branch::query()->create([
        'name' => 'Tenant A Other Branch',
        'timezone' => 'Asia/Yerevan',
        'status' => 'active',
    ]);

    app(BranchContext::class)->set((int) $otherBranch->id);
    app(CreateMenuItem::class)((int) $category->id, menuQueryText('Other Branch Omelette', 'Այլ ձվածեղ', 'Другой омлет'), null, new Money(180000, 'AMD'));

    app(BranchContext::class)->set((int) $records['branch']->id);

    $armenian = app(SearchMenuItems::class)('ձվածեղ');
    $russian = app(SearchMenuItems::class)('омлет');
    $activeArchived = app(SearchMenuItems::class)('archived');
    $empty = app(SearchMenuItems::class)('   ');
    $withInactive = app(SearchMenuItems::class)('soup', includeInactive: true);
    $archived = app(SearchMenuItems::class)('archived', archiveMode: 'archived');
    $allArchived = app(SearchMenuItems::class)('archived', archiveMode: 'all');

    expect($armenian->total())->toBe(1)
        ->and($armenian->items()[0]->translatedName()->forLocale('en'))->toBe('Lori Omelette')
        ->and($russian->total())->toBe(1)
        ->and($activeArchived->total())->toBe(0)
        ->and($empty->total())->toBe(0)
        ->and($withInactive->total())->toBe(1)
        ->and($withInactive->items()[0]->active)->toBeFalse()
        ->and($archived->total())->toBe(1)
        ->and(collect($archived->items())->every(fn (MenuItem $item): bool => $item->trashed()))->toBeTrue()
        ->and($allArchived->total())->toBe(1);
});

it('requires branch context for paginated item query actions', function (): void {
    $records = menuQueryTenant('tenant-a', 'Tenant A');

    app(TenantResolver::class)->set((int) $records['tenant']->id);
    app(BranchContext::class)->set((int) $records['branch']->id);

    $root = app(CreateMenuCategory::class)(menuQueryText('Menu'));
    $category = app(CreateMenuCategory::class)(menuQueryText('Breakfast'), parentId: (int) $root->id);

    app(BranchContext::class)->clear();

    expect(fn () => app(PaginateMenuItems::class)((int) $category->id))
        ->toThrow(MenuDomainException::class, 'Menu item operations require a resolved branch context.')
        ->and(fn () => app(SearchMenuItems::class)('omelette'))
        ->toThrow(MenuDomainException::class, 'Menu item operations require a resolved branch context.');
});

/**
 * @return array{tenant: Tenant, branch: Branch}
 */
function menuQueryTenant(string $slug, string $name): array
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

function menuQueryText(string $en, ?string $hy = null, ?string $ru = null): LocalizedText
{
    return LocalizedText::fromArray([
        'hy' => $hy ?? $en,
        'ru' => $ru ?? $en,
        'en' => $en,
    ]);
}
