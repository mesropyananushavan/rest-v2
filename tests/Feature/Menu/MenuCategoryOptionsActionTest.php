<?php

declare(strict_types=1);

use App\Modules\Menu\Application\ArchiveMenuCategory;
use App\Modules\Menu\Application\CreateMenuCategory;
use App\Modules\Menu\Application\SearchMenuCategoryOptions;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Modules\Tenancy\Infrastructure\Models\Branch;
use App\Modules\Tenancy\Infrastructure\Models\Tenant;
use App\Support\I18n\LocalizedText;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

afterEach(function (): void {
    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();
});

it('searches root category options with archive filtering and self exclusion', function (): void {
    $records = menuCategoryOptionsActionTenant('tenant-a', 'Tenant A');

    app(TenantResolver::class)->set((int) $records['tenant']->id);
    app(BranchContext::class)->set((int) $records['branch']->id);

    $breakfastRoot = app(CreateMenuCategory::class)(menuCategoryOptionsActionText('Breakfast menu'), sortOrder: 10);
    $dinnerRoot = app(CreateMenuCategory::class)(menuCategoryOptionsActionText('Dinner menu'), sortOrder: 20);
    $subcategory = app(CreateMenuCategory::class)(menuCategoryOptionsActionText('Breakfast plates'), parentId: (int) $breakfastRoot->id);
    $archivedRoot = app(CreateMenuCategory::class)(menuCategoryOptionsActionText('Archived menu'), sortOrder: 30);

    app(ArchiveMenuCategory::class)((int) $archivedRoot->id);

    $result = app(SearchMenuCategoryOptions::class)(
        SearchMenuCategoryOptions::MODE_ROOTS,
        search: 'menu',
        excludeId: (int) $breakfastRoot->id,
    );

    expect($result['options'])->toBe([
        ['id' => (int) $dinnerRoot->id, 'label' => 'Dinner menu'],
    ])
        ->and(collect($result['options'])->pluck('id')->all())
        ->not->toContain((int) $subcategory->id)
        ->not->toContain((int) $archivedRoot->id);
});

it('searches subcategory options with parent labels and excludes roots and archived rows', function (): void {
    $records = menuCategoryOptionsActionTenant('tenant-a', 'Tenant A');

    app(TenantResolver::class)->set((int) $records['tenant']->id);
    app(BranchContext::class)->set((int) $records['branch']->id);

    $root = app(CreateMenuCategory::class)(menuCategoryOptionsActionText('Menu'), sortOrder: 1);
    $breakfast = app(CreateMenuCategory::class)(menuCategoryOptionsActionText('Breakfast plates', hy: 'Նախաճաշ'), sortOrder: 10, parentId: (int) $root->id);
    $archived = app(CreateMenuCategory::class)(menuCategoryOptionsActionText('Archived plates'), sortOrder: 20, parentId: (int) $root->id);

    app(ArchiveMenuCategory::class)((int) $archived->id);

    $result = app(SearchMenuCategoryOptions::class)(
        SearchMenuCategoryOptions::MODE_SUBCATEGORIES,
        search: 'Նախաճաշ',
    );

    expect($result['options'])->toBe([
        ['id' => (int) $breakfast->id, 'label' => 'Menu / Breakfast plates'],
    ])
        ->and(collect($result['options'])->pluck('id')->all())
        ->not->toContain((int) $root->id)
        ->not->toContain((int) $archived->id);
});

it('keeps menu category option lookup tenant scoped', function (): void {
    $tenantA = menuCategoryOptionsActionTenant('tenant-a', 'Tenant A');
    $tenantB = menuCategoryOptionsActionTenant('tenant-b', 'Tenant B');

    app(TenantResolver::class)->set((int) $tenantA['tenant']->id);
    app(BranchContext::class)->set((int) $tenantA['branch']->id);

    $tenantARoot = app(CreateMenuCategory::class)(menuCategoryOptionsActionText('Tenant A menu'));

    app(TenantResolver::class)->set((int) $tenantB['tenant']->id);
    app(BranchContext::class)->set((int) $tenantB['branch']->id);

    $tenantBRoot = app(CreateMenuCategory::class)(menuCategoryOptionsActionText('Tenant B menu'));

    app(TenantResolver::class)->set((int) $tenantA['tenant']->id);
    app(BranchContext::class)->set((int) $tenantA['branch']->id);

    $result = app(SearchMenuCategoryOptions::class)(SearchMenuCategoryOptions::MODE_ROOTS, search: 'menu');

    expect($result['options'])->toBe([
        ['id' => (int) $tenantARoot->id, 'label' => 'Tenant A menu'],
    ])
        ->and(collect($result['options'])->pluck('id')->all())->not->toContain((int) $tenantBRoot->id);
});

it('paginates menu category options with stable boundary pages', function (): void {
    $records = menuCategoryOptionsActionTenant('tenant-a', 'Tenant A');

    app(TenantResolver::class)->set((int) $records['tenant']->id);
    app(BranchContext::class)->set((int) $records['branch']->id);

    for ($index = 1; $index <= 12; $index++) {
        app(CreateMenuCategory::class)(
            menuCategoryOptionsActionText(str_pad((string) $index, 2, '0', STR_PAD_LEFT).' Root'),
            sortOrder: $index,
        );
    }

    $firstPage = app(SearchMenuCategoryOptions::class)(SearchMenuCategoryOptions::MODE_ROOTS, perPage: 5, page: 1);
    $thirdPage = app(SearchMenuCategoryOptions::class)(SearchMenuCategoryOptions::MODE_ROOTS, perPage: 5, page: 3);
    $boundaryPage = app(SearchMenuCategoryOptions::class)(SearchMenuCategoryOptions::MODE_ROOTS, perPage: 5, page: 4);

    expect($firstPage['options'])->toHaveCount(5)
        ->and($firstPage['options'][0]['label'])->toBe('01 Root')
        ->and($firstPage['has_more'])->toBeTrue()
        ->and($firstPage['next_page'])->toBe(2)
        ->and($thirdPage['options'])->toHaveCount(2)
        ->and($thirdPage['has_more'])->toBeFalse()
        ->and($thirdPage['next_page'])->toBeNull()
        ->and($boundaryPage['options'])->toBe([])
        ->and($boundaryPage['has_more'])->toBeFalse()
        ->and($boundaryPage['next_page'])->toBeNull();
});

/**
 * @return array{tenant: Tenant, branch: Branch}
 */
function menuCategoryOptionsActionTenant(string $slug, string $name): array
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

function menuCategoryOptionsActionText(string $en, ?string $hy = null, ?string $ru = null): LocalizedText
{
    return LocalizedText::fromArray([
        'hy' => $hy ?? $en,
        'ru' => $ru ?? $en,
        'en' => $en,
    ]);
}
