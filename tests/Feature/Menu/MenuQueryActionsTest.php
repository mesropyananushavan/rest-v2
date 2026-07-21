<?php

declare(strict_types=1);

use App\Modules\Menu\Application\ArchiveMenuItem;
use App\Modules\Menu\Application\CreateMenuCategory;
use App\Modules\Menu\Application\CreateMenuItem;
use App\Modules\Menu\Application\PaginateMenuCategories;
use App\Modules\Menu\Application\PaginateMenuItems;
use App\Modules\Menu\Application\SearchMenuItems;
use App\Modules\Menu\Domain\MenuDomainException;
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

it('paginates category panel results and searches localized names', function (): void {
    $records = menuQueryTenant('tenant-a', 'Tenant A');

    app(TenantResolver::class)->set((int) $records['tenant']->id);
    app(BranchContext::class)->set((int) $records['branch']->id);

    for ($index = 1; $index <= 30; $index++) {
        app(CreateMenuCategory::class)(
            menuQueryText("Category {$index}", "Բաժին {$index}", "Категория {$index}"),
            sortOrder: $index,
        );
    }

    $firstPage = app(PaginateMenuCategories::class)(perPage: 10, page: 1);
    $secondPage = app(PaginateMenuCategories::class)(perPage: 10, page: 2);
    $armenianSearch = app(PaginateMenuCategories::class)(search: 'Բաժին 2', perPage: 25);

    expect($firstPage->total())->toBe(30)
        ->and($firstPage->count())->toBe(10)
        ->and($firstPage->items()[0]->translatedName()->forLocale('en'))->toBe('Category 1')
        ->and($secondPage->items()[0]->translatedName()->forLocale('en'))->toBe('Category 11')
        ->and($armenianSearch->total())->toBe(11)
        ->and(collect($armenianSearch->items())->pluck('translated_name')->flatten()->contains('Բաժին 20'))->toBeTrue();
});

it('paginates selected-category items and applies inactive and archive filters', function (): void {
    $records = menuQueryTenant('tenant-a', 'Tenant A');

    app(TenantResolver::class)->set((int) $records['tenant']->id);
    app(BranchContext::class)->set((int) $records['branch']->id);

    $category = app(CreateMenuCategory::class)(menuQueryText('Breakfast'));

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
    $withArchived = app(PaginateMenuItems::class)((int) $category->id, includeInactive: true, includeArchived: true, perPage: 50);

    expect($activeOnly->total())->toBe(25)
        ->and($activeOnly->count())->toBe(25)
        ->and($withInactive->total())->toBe(31)
        ->and($withArchived->total())->toBe(32)
        ->and(collect($activeOnly->items())->contains(fn (MenuItem $item): bool => (int) $item->sort_order === 5))->toBeFalse()
        ->and(collect($withArchived->items())->contains(fn (MenuItem $item): bool => $item->trashed()))->toBeTrue();
});

it('searches menu items across all localized names within the current branch only', function (): void {
    $records = menuQueryTenant('tenant-a', 'Tenant A');

    app(TenantResolver::class)->set((int) $records['tenant']->id);
    app(BranchContext::class)->set((int) $records['branch']->id);

    $category = app(CreateMenuCategory::class)(menuQueryText('Breakfast'));
    app(CreateMenuItem::class)((int) $category->id, menuQueryText('Lori Omelette', 'Լոռի ձվածեղ', 'Лорийский омлет'), null, new Money(180000, 'AMD'));
    app(CreateMenuItem::class)((int) $category->id, menuQueryText('Hidden Soup'), null, new Money(90000, 'AMD'), active: false);

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
    $empty = app(SearchMenuItems::class)('   ');
    $withInactive = app(SearchMenuItems::class)('soup', includeInactive: true);

    expect($armenian->total())->toBe(1)
        ->and($armenian->items()[0]->translatedName()->forLocale('en'))->toBe('Lori Omelette')
        ->and($russian->total())->toBe(1)
        ->and($empty->total())->toBe(0)
        ->and($withInactive->total())->toBe(1)
        ->and($withInactive->items()[0]->active)->toBeFalse();
});

it('requires branch context for paginated item query actions', function (): void {
    $records = menuQueryTenant('tenant-a', 'Tenant A');

    app(TenantResolver::class)->set((int) $records['tenant']->id);
    app(BranchContext::class)->set((int) $records['branch']->id);

    $category = app(CreateMenuCategory::class)(menuQueryText('Breakfast'));

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
