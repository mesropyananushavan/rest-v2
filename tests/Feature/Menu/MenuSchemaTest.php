<?php

declare(strict_types=1);

use App\Modules\Menu\Infrastructure\Models\MenuCategory;
use App\Modules\Menu\Infrastructure\Models\MenuItem;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Modules\Tenancy\Infrastructure\Models\Branch;
use App\Modules\Tenancy\Infrastructure\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

afterEach(function (): void {
    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();
});

it('stores menu categories and items as tenant-scoped records with integer money', function (): void {
    $tenant = Tenant::query()->create([
        'name' => 'Tenant A',
        'slug' => 'tenant-a',
        'default_locale' => 'hy',
        'currency' => 'AMD',
        'status' => 'active',
    ]);

    app(TenantResolver::class)->set((int) $tenant->id);

    $branch = Branch::query()->create([
        'name' => 'Tenant A Branch',
        'timezone' => 'Asia/Yerevan',
        'status' => 'active',
    ]);

    app(BranchContext::class)->set((int) $branch->id);

    $category = MenuCategory::query()->create([
        'translated_name' => [
            'hy' => 'Աղցաններ',
            'ru' => 'Салаты',
            'en' => 'Salads',
        ],
        'sort_order' => 10,
        'active' => true,
    ]);

    $item = MenuItem::query()->create([
        'branch_id' => (int) $branch->id,
        'category_id' => (int) $category->id,
        'translated_name' => [
            'hy' => 'Ամառային աղցան',
            'ru' => 'Летний салат',
            'en' => 'Summer salad',
        ],
        'translated_description' => [
            'hy' => 'Թարմ բանջարեղեն',
            'ru' => 'Свежие овощи',
            'en' => 'Fresh vegetables',
        ],
        'price_minor' => 250000,
        'currency' => 'AMD',
        'active' => true,
    ]);

    expect((int) $category->tenant_id)->toBe((int) $tenant->id)
        ->and((int) $item->tenant_id)->toBe((int) $tenant->id)
        ->and($category->translatedName()['en'])->toBe('Salads')
        ->and($item->translatedDescription()['en'])->toBe('Fresh vegetables')
        ->and($item->price()->minor)->toBe(250000)
        ->and($item->price()->currency)->toBe('AMD');
});

it('prevents menu records from leaking across tenants through tenant-scoped models', function (): void {
    $tenantA = tenantWithMenuRecords('tenant-a', 'Tenant A');
    $tenantB = tenantWithMenuRecords('tenant-b', 'Tenant B');

    app(TenantResolver::class)->set((int) $tenantA['tenant']->id);

    expect(MenuCategory::query()->pluck('id')->all())->toBe([(int) $tenantA['category']->id])
        ->and(MenuItem::query()->pluck('id')->all())->toBe([(int) $tenantA['item']->id])
        ->and(MenuCategory::query()->find((int) $tenantB['category']->id))->toBeNull()
        ->and(MenuItem::query()->find((int) $tenantB['item']->id))->toBeNull();

    app(TenantResolver::class)->clear();

    expect(MenuCategory::query()->count())->toBe(0)
        ->and(MenuItem::query()->count())->toBe(0);
});

/**
 * @return array{tenant: Tenant, branch: Branch, category: MenuCategory, item: MenuItem}
 */
function tenantWithMenuRecords(string $slug, string $name): array
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

    $category = MenuCategory::query()->create([
        'translated_name' => ['hy' => $name, 'ru' => $name, 'en' => $name],
        'active' => true,
    ]);

    $item = MenuItem::query()->create([
        'branch_id' => (int) $branch->id,
        'category_id' => (int) $category->id,
        'translated_name' => ['hy' => "{$name} Item", 'ru' => "{$name} Item", 'en' => "{$name} Item"],
        'translated_description' => ['hy' => "{$name} Description", 'ru' => "{$name} Description", 'en' => "{$name} Description"],
        'price_minor' => 100000,
        'currency' => 'AMD',
        'active' => true,
    ]);

    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();

    return [
        'tenant' => $tenant,
        'branch' => $branch,
        'category' => $category,
        'item' => $item,
    ];
}
