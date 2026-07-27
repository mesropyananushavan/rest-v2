<?php

declare(strict_types=1);

use App\Modules\Menu\Application\ArchiveMenuItem;
use App\Modules\Menu\Application\CreateMenuCategory;
use App\Modules\Menu\Application\CreateMenuItem;
use App\Modules\Menu\Contracts\MenuCatalog;
use App\Modules\Menu\Contracts\SellableMenuItem;
use App\Modules\Menu\Infrastructure\Models\MenuCategory;
use App\Modules\Menu\Infrastructure\Models\MenuItem;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Modules\Tenancy\Infrastructure\Models\Branch;
use App\Modules\Tenancy\Infrastructure\Models\Tenant;
use App\Support\I18n\LocalizedText;
use App\Support\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

afterEach(function (): void {
    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();
});

it('browses only sellable items for the active tenant and branch through MenuCatalog', function (): void {
    $tenantA = menuCatalogBrowseTenant('tenant-a', branchCount: 2);
    $tenantB = menuCatalogBrowseTenant('tenant-b');

    menuCatalogBrowseActingIn($tenantA, 0);
    $visibleCategory = menuCatalogBrowseCategory('Visible Menu', 'Visible Category')['category'];
    $visible = menuCatalogBrowseItem($visibleCategory, $tenantA['branches'][0], 'Visible Dish');
    $inactive = menuCatalogBrowseItem($visibleCategory, $tenantA['branches'][0], 'Inactive Dish', active: false);
    $archived = menuCatalogBrowseItem($visibleCategory, $tenantA['branches'][0], 'Archived Dish');
    app(ArchiveMenuItem::class)((int) $archived->id);
    $otherBranch = menuCatalogBrowseItem($visibleCategory, $tenantA['branches'][1], 'Other Branch Dish');

    menuCatalogBrowseActingIn($tenantB, 0);
    $foreignCategory = menuCatalogBrowseCategory('Foreign Menu', 'Foreign Category')['category'];
    $foreign = menuCatalogBrowseItem($foreignCategory, $tenantB['branches'][0], 'Foreign Dish');

    menuCatalogBrowseActingIn($tenantA, 0);
    $result = app(MenuCatalog::class)->browseSellableInBranch((int) $tenantA['branches'][0]->id);

    expect($result->items)->toHaveCount(1)
        ->and($result->items[0])->toBeInstanceOf(SellableMenuItem::class)
        ->and($result->items[0]->id)->toBe((int) $visible->id)
        ->and($result->items[0]->name->forLocale('en'))->toBe('Visible Dish')
        ->and($result->items[0]->price->minor)->toBe(123400)
        ->and(collect($result->items)->pluck('id')->all())->not->toContain((int) $inactive->id)
        ->and(collect($result->items)->pluck('id')->all())->not->toContain((int) $archived->id)
        ->and(collect($result->items)->pluck('id')->all())->not->toContain((int) $otherBranch->id)
        ->and(collect($result->items)->pluck('id')->all())->not->toContain((int) $foreign->id)
        ->and($result->categoryGroups)->toHaveCount(1)
        ->and($result->categoryGroups[0]->categories)->toHaveCount(1)
        ->and($result->categoryGroups[0]->categories[0]->id)->toBe((int) $visibleCategory->id);
});

it('searches localized names across hy ru and en while escaping LIKE wildcards', function (): void {
    $tenant = menuCatalogBrowseTenant('tenant-a');

    menuCatalogBrowseActingIn($tenant, 0);
    $category = menuCatalogBrowseCategory('Search Menu', 'Search Category')['category'];
    $armenian = menuCatalogBrowseItem($category, $tenant['branches'][0], 'Khash', hy: 'Խաշ', ru: 'Хаш');
    $russian = menuCatalogBrowseItem($category, $tenant['branches'][0], 'Borscht', hy: 'Բորշչ', ru: 'Борщ');
    $english = menuCatalogBrowseItem($category, $tenant['branches'][0], 'Burger', hy: 'Բուրգեր', ru: 'Бургер');
    $literal = menuCatalogBrowseItem($category, $tenant['branches'][0], '100%_\\ Sauce');
    $decoy = menuCatalogBrowseItem($category, $tenant['branches'][0], '100XY Sauce');

    $armenianResult = app(MenuCatalog::class)->browseSellableInBranch((int) $tenant['branches'][0]->id, search: 'Խաշ');
    $russianResult = app(MenuCatalog::class)->browseSellableInBranch((int) $tenant['branches'][0]->id, search: 'Борщ');
    $englishResult = app(MenuCatalog::class)->browseSellableInBranch((int) $tenant['branches'][0]->id, search: 'Burger');
    $wildcardResult = app(MenuCatalog::class)->browseSellableInBranch((int) $tenant['branches'][0]->id, search: '%_\\');

    expect(collect($armenianResult->items)->pluck('id')->all())->toBe([(int) $armenian->id])
        ->and(collect($russianResult->items)->pluck('id')->all())->toBe([(int) $russian->id])
        ->and(collect($englishResult->items)->pluck('id')->all())->toBe([(int) $english->id])
        ->and(collect($wildcardResult->items)->pluck('id')->all())->toBe([(int) $literal->id])
        ->and(collect($wildcardResult->items)->pluck('id')->all())->not->toContain((int) $decoy->id);
});

it('normalizes invalid empty and foreign category filters without leaking category existence', function (): void {
    $tenantA = menuCatalogBrowseTenant('tenant-a');
    $tenantB = menuCatalogBrowseTenant('tenant-b');

    menuCatalogBrowseActingIn($tenantA, 0);
    $breakfast = menuCatalogBrowseCategory('Tenant A Menu', 'Breakfast')['category'];
    $lunch = menuCatalogBrowseCategory('Tenant A Other Menu', 'Lunch')['category'];
    $empty = menuCatalogBrowseCategory('Tenant A Empty Menu', 'Empty')['category'];
    $root = menuCatalogBrowseCategory('Tenant A Root Only', 'Unused')['root'];
    $breakfastItem = menuCatalogBrowseItem($breakfast, $tenantA['branches'][0], 'Breakfast Dish');
    $lunchItem = menuCatalogBrowseItem($lunch, $tenantA['branches'][0], 'Lunch Dish');

    menuCatalogBrowseActingIn($tenantB, 0);
    $foreignCategory = menuCatalogBrowseCategory('Foreign Menu', 'Foreign Category')['category'];
    $foreignItem = menuCatalogBrowseItem($foreignCategory, $tenantB['branches'][0], 'Foreign Dish');

    menuCatalogBrowseActingIn($tenantA, 0);
    $selected = app(MenuCatalog::class)->browseSellableInBranch((int) $tenantA['branches'][0]->id, categoryId: (int) $breakfast->id);
    $emptySelection = app(MenuCatalog::class)->browseSellableInBranch((int) $tenantA['branches'][0]->id, categoryId: (int) $empty->id);
    $rootSelection = app(MenuCatalog::class)->browseSellableInBranch((int) $tenantA['branches'][0]->id, categoryId: (int) $root->id);
    $foreignSelection = app(MenuCatalog::class)->browseSellableInBranch((int) $tenantA['branches'][0]->id, categoryId: (int) $foreignCategory->id);

    expect($selected->selectedCategoryId)->toBe((int) $breakfast->id)
        ->and(collect($selected->items)->pluck('id')->all())->toBe([(int) $breakfastItem->id])
        ->and($emptySelection->selectedCategoryId)->toBeNull()
        ->and($rootSelection->selectedCategoryId)->toBeNull()
        ->and($foreignSelection->selectedCategoryId)->toBeNull()
        ->and(collect($foreignSelection->items)->pluck('id')->all())->toContain((int) $breakfastItem->id, (int) $lunchItem->id)
        ->and(collect($foreignSelection->items)->pluck('id')->all())->not->toContain((int) $foreignItem->id);
});

it('bounds item and category pagination to fifty records', function (): void {
    $tenant = menuCatalogBrowseTenant('tenant-a');

    menuCatalogBrowseActingIn($tenant, 0);

    for ($index = 1; $index <= 55; $index++) {
        $category = menuCatalogBrowseCategory("Root {$index}", "Category {$index}")['category'];
        menuCatalogBrowseItem($category, $tenant['branches'][0], "Dish {$index}", sortOrder: $index);
    }

    $result = app(MenuCatalog::class)->browseSellableInBranch(
        (int) $tenant['branches'][0]->id,
        perPage: 100,
        categoryPerPage: 100,
    );

    $categoryCount = collect($result->categoryGroups)
        ->sum(fn ($group): int => count($group->categories));

    expect($result->items)->toHaveCount(50)
        ->and($result->hasMoreItemPages)->toBeTrue()
        ->and($categoryCount)->toBe(50)
        ->and($result->hasMoreCategoryPages)->toBeTrue();
});

it('keeps sellable menu browse query count fixed as result sizes grow', function (): void {
    $tenant = menuCatalogBrowseTenant('tenant-a');

    menuCatalogBrowseActingIn($tenant, 0);
    $category = menuCatalogBrowseCategory('Query Menu', 'Query Category')['category'];

    for ($index = 1; $index <= 30; $index++) {
        menuCatalogBrowseItem($category, $tenant['branches'][0], "Query Dish {$index}", sortOrder: $index);
    }

    [$smallResult, $smallQueryCount] = menuCatalogBrowseQueryCount(
        fn () => app(MenuCatalog::class)->browseSellableInBranch((int) $tenant['branches'][0]->id, perPage: 5, categoryPerPage: 5),
    );
    [$largeResult, $largeQueryCount] = menuCatalogBrowseQueryCount(
        fn () => app(MenuCatalog::class)->browseSellableInBranch((int) $tenant['branches'][0]->id, perPage: 30, categoryPerPage: 30),
    );

    expect($smallResult->items)->toHaveCount(5)
        ->and($largeResult->items)->toHaveCount(30)
        ->and($smallQueryCount)->toBe($largeQueryCount)
        ->and($smallQueryCount)->toBeLessThanOrEqual(5);
});

/**
 * @return array{tenant: Tenant, branches: list<Branch>}
 */
function menuCatalogBrowseTenant(string $slug, int $branchCount = 1): array
{
    $tenant = Tenant::query()->create([
        'name' => str($slug)->headline()->toString(),
        'slug' => $slug,
        'default_locale' => 'hy',
        'currency' => 'AMD',
        'status' => 'active',
    ]);

    app(TenantResolver::class)->set((int) $tenant->id);

    $branches = [];

    for ($index = 1; $index <= $branchCount; $index++) {
        $branches[] = Branch::query()->create([
            'name' => "{$slug} Branch {$index}",
            'timezone' => 'Asia/Yerevan',
            'status' => 'active',
        ]);
    }

    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();

    return [
        'tenant' => $tenant,
        'branches' => $branches,
    ];
}

/**
 * @param  array{tenant: Tenant, branches: list<Branch>}  $record
 */
function menuCatalogBrowseActingIn(array $record, int $branchIndex): void
{
    app(TenantResolver::class)->set((int) $record['tenant']->id);
    app(BranchContext::class)->set((int) $record['branches'][$branchIndex]->id);
}

/**
 * @return array{root: MenuCategory, category: MenuCategory}
 */
function menuCatalogBrowseCategory(string $rootName, string $categoryName, bool $rootActive = true, bool $categoryActive = true): array
{
    $root = app(CreateMenuCategory::class)(
        menuCatalogBrowseText($rootName),
        active: $rootActive,
    );
    $category = app(CreateMenuCategory::class)(
        menuCatalogBrowseText($categoryName),
        active: $categoryActive,
        parentId: (int) $root->id,
    );

    return [
        'root' => $root,
        'category' => $category,
    ];
}

function menuCatalogBrowseItem(
    MenuCategory $category,
    Branch $branch,
    string $en,
    ?string $hy = null,
    ?string $ru = null,
    int $priceMinor = 123400,
    int $sortOrder = 0,
    bool $active = true,
): MenuItem {
    app(BranchContext::class)->set((int) $branch->id);

    return app(CreateMenuItem::class)(
        (int) $category->id,
        menuCatalogBrowseText($en, $hy, $ru),
        null,
        new Money($priceMinor, 'AMD'),
        sortOrder: $sortOrder,
        active: $active,
    );
}

function menuCatalogBrowseText(string $en, ?string $hy = null, ?string $ru = null): LocalizedText
{
    return LocalizedText::fromArray([
        'hy' => $hy ?? $en,
        'ru' => $ru ?? $en,
        'en' => $en,
    ]);
}

/**
 * @template TReturn
 *
 * @param  callable(): TReturn  $callback
 * @return array{0: TReturn, 1: int}
 */
function menuCatalogBrowseQueryCount(callable $callback): array
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    try {
        $result = $callback();

        return [$result, count(DB::getQueryLog())];
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }
}
