<?php

declare(strict_types=1);

use App\Modules\Menu\Infrastructure\Models\MenuCategory;
use App\Modules\Menu\Infrastructure\Models\MenuItem;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Modules\Tenancy\Infrastructure\Models\Branch;
use App\Modules\Tenancy\Infrastructure\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
        ->and($category->translatedName()->forLocale('en'))->toBe('Salads')
        ->and($item->translatedDescription()?->forLocale('en'))->toBe('Fresh vegetables')
        ->and($item->price()->minor)->toBe(250000)
        ->and($item->price()->currency)->toBe('AMD');
});

it('stores menu archive columns and deleted-at-aware indexes', function (): void {
    expect(Schema::hasColumn('menu_categories', 'deleted_at'))->toBeTrue()
        ->and(Schema::hasColumn('menu_categories', 'parent_id'))->toBeTrue()
        ->and(Schema::hasColumn('menu_categories', 'archived_with_category_id'))->toBeTrue()
        ->and(Schema::hasColumn('menu_items', 'deleted_at'))->toBeTrue()
        ->and(Schema::hasColumn('menu_items', 'archived_with_category_id'))->toBeTrue();

    $categoryIndexes = collect(Schema::getIndexes('menu_categories'))->pluck('columns')->all();
    $itemIndexes = collect(Schema::getIndexes('menu_items'))->pluck('columns')->all();

    expect($categoryIndexes)->toContain(['tenant_id', 'deleted_at', 'active', 'sort_order'])
        ->and($categoryIndexes)->toContain(['tenant_id', 'parent_id', 'deleted_at', 'active', 'sort_order', 'id'])
        ->and($categoryIndexes)->toContain(['tenant_id', 'archived_with_category_id', 'deleted_at'])
        ->and($itemIndexes)->toContain(['tenant_id', 'branch_id', 'deleted_at', 'active'])
        ->and($itemIndexes)->toContain(['tenant_id', 'category_id', 'deleted_at', 'sort_order'])
        ->and($itemIndexes)->toContain(['tenant_id', 'archived_with_category_id', 'deleted_at']);
});

it('stores menu category tree foreign keys and self-parent check on PostgreSQL', function (): void {
    if (Schema::getConnection()->getDriverName() !== 'pgsql') {
        expect(true)->toBeTrue();

        return;
    }

    $constraints = collect(DB::select(<<<'SQL'
        select conname, contype, pg_get_constraintdef(oid) as definition
        from pg_constraint
        where conrelid = 'menu_categories'::regclass
        SQL))
        ->mapWithKeys(fn (stdClass $constraint): array => [
            (string) $constraint->conname => [
                'type' => (string) $constraint->contype,
                'definition' => (string) $constraint->definition,
            ],
        ]);

    expect($constraints->get('menu_categories_parent_id_foreign'))
        ->toMatchArray([
            'type' => 'f',
            'definition' => 'FOREIGN KEY (parent_id) REFERENCES menu_categories(id) ON DELETE RESTRICT',
        ])
        ->and($constraints->get('menu_categories_archived_with_category_id_foreign'))
        ->toMatchArray([
            'type' => 'f',
            'definition' => 'FOREIGN KEY (archived_with_category_id) REFERENCES menu_categories(id) ON DELETE RESTRICT',
        ])
        ->and($constraints->get('menu_categories_parent_not_self_chk'))
        ->toMatchArray([
            'type' => 'c',
            'definition' => 'CHECK (((parent_id IS NULL) OR (parent_id <> id)))',
        ]);
});

it('models menu category parent subcategories and archive marker relations', function (): void {
    $tenant = Tenant::query()->create([
        'name' => 'Tenant Tree',
        'slug' => 'tenant-tree',
        'default_locale' => 'hy',
        'currency' => 'AMD',
        'status' => 'active',
    ]);

    app(TenantResolver::class)->set((int) $tenant->id);

    $root = MenuCategory::query()->create([
        'translated_name' => ['hy' => 'Ճաշացանկ', 'ru' => 'Меню', 'en' => 'Menu'],
        'sort_order' => 10,
        'active' => true,
    ]);

    $subcategory = MenuCategory::query()->create([
        'parent_id' => (int) $root->id,
        'archived_with_category_id' => (int) $root->id,
        'translated_name' => ['hy' => 'Աղցաններ', 'ru' => 'Салаты', 'en' => 'Salads'],
        'sort_order' => 20,
        'active' => true,
    ]);

    $subcategory = MenuCategory::query()->with(['parent', 'archivedWithCategory'])->findOrFail((int) $subcategory->id);
    $root = MenuCategory::query()->with('subcategories')->findOrFail((int) $root->id);

    expect($subcategory->parent_id)->toBe((int) $root->id)
        ->and($subcategory->archived_with_category_id)->toBe((int) $root->id)
        ->and($subcategory->parent?->is($root))->toBeTrue()
        ->and($subcategory->archivedWithCategory?->is($root))->toBeTrue()
        ->and($root->subcategories->pluck('id')->all())->toBe([(int) $subcategory->id]);
});

it('stores optional menu item image metadata without changing query indexes', function (): void {
    expect(Schema::hasColumn('menu_items', 'internal_image'))->toBeTrue()
        ->and(Schema::hasColumn('menu_items', 'public_image'))->toBeTrue();

    $tenant = Tenant::query()->create([
        'name' => 'Tenant Images',
        'slug' => 'tenant-images',
        'default_locale' => 'hy',
        'currency' => 'AMD',
        'status' => 'active',
    ]);

    app(TenantResolver::class)->set((int) $tenant->id);

    $branch = Branch::query()->create([
        'name' => 'Tenant Images Branch',
        'timezone' => 'Asia/Yerevan',
        'status' => 'active',
    ]);

    app(BranchContext::class)->set((int) $branch->id);

    $category = MenuCategory::query()->create([
        'translated_name' => ['hy' => 'Նկարներ', 'ru' => 'Изображения', 'en' => 'Images'],
        'active' => true,
    ]);

    $item = MenuItem::query()->create([
        'branch_id' => (int) $branch->id,
        'category_id' => (int) $category->id,
        'translated_name' => ['hy' => 'Թեստ', 'ru' => 'Тест', 'en' => 'Test'],
        'price_minor' => 100000,
        'currency' => 'AMD',
        'internal_image' => [
            'path' => 'tenants/1/menu/items/1/internal/original.webp',
            'thumbnail_path' => 'tenants/1/menu/items/1/internal/thumb.webp',
            'mime_type' => 'image/webp',
            'width' => 800,
            'height' => 600,
            'size' => 12345,
        ],
        'public_image' => null,
        'active' => true,
    ]);

    expect($item->internal_image['thumbnail_path'] ?? null)->toBe('tenants/1/menu/items/1/internal/thumb.webp')
        ->and($item->public_image)->toBeNull();
});

it('stores menu search and pagination indexes', function (): void {
    $categoryIndexes = collect(Schema::getIndexes('menu_categories'))->pluck('columns')->all();
    $itemIndexes = collect(Schema::getIndexes('menu_items'))->pluck('columns')->all();

    expect($categoryIndexes)->toContain(['tenant_id', 'deleted_at', 'sort_order', 'id'])
        ->and($itemIndexes)->toContain(['tenant_id', 'branch_id', 'category_id', 'deleted_at', 'active', 'sort_order', 'id'])
        ->and($itemIndexes)->toContain(['tenant_id', 'branch_id', 'category_id', 'deleted_at', 'sort_order', 'id'])
        ->and($itemIndexes)->toContain(['tenant_id', 'branch_id', 'deleted_at', 'active', 'sort_order', 'id']);
});

it('creates PostgreSQL trigram expression indexes for localized menu search', function (): void {
    if (Schema::getConnection()->getDriverName() !== 'pgsql') {
        expect(true)->toBeTrue();

        return;
    }

    $indexes = collect(DB::select("select indexname, indexdef from pg_indexes where schemaname = 'public' and tablename in ('menu_categories', 'menu_items')"))
        ->mapWithKeys(fn (stdClass $index): array => [(string) $index->indexname => (string) $index->indexdef]);

    expect($indexes->get('menu_categories_translated_name_trgm_idx'))
        ->toContain('USING gin')
        ->toContain('gin_trgm_ops')
        ->toContain("translated_name ->> 'hy'")
        ->and($indexes->get('menu_items_translated_name_trgm_idx'))
        ->toContain('USING gin')
        ->toContain('gin_trgm_ops')
        ->toContain("translated_name ->> 'ru'");
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
