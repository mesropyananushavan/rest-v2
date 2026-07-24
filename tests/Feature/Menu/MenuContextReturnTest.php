<?php

declare(strict_types=1);

use App\Livewire\Admin\MenuItemForm;
use App\Modules\Identity\Infrastructure\Models\Permission;
use App\Modules\Identity\Infrastructure\Models\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Identity\Infrastructure\Models\UserBranchAssignment;
use App\Modules\Menu\Application\ArchiveMenuCategory;
use App\Modules\Menu\Application\CreateMenuCategory;
use App\Modules\Menu\Application\CreateMenuItem;
use App\Modules\Menu\Infrastructure\Models\MenuCategory;
use App\Modules\Menu\Infrastructure\Models\MenuItem;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Modules\Tenancy\Infrastructure\Models\Branch;
use App\Modules\Tenancy\Infrastructure\Models\Tenant;
use App\Support\I18n\LocalizedText;
use App\Support\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

afterEach(function (): void {
    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();
});

it('returns category create and edit save and cancel paths to the originating menu context', function (): void {
    $records = menuContextReturnRecords(superadmin: true);
    $context = menuContextReturnQuery((int) $records['category']->id, archiveMode: 'all');
    $expectedReturnUrl = menuContextReturnUrl((int) $records['category']->id, archiveMode: 'all');
    $createUrl = route('admin.menu.categories.create', ['context' => $context]);

    $this->actingAs($records['user'])
        ->withSession(['branch_id' => (int) $records['branch']->id])
        ->get($createUrl)
        ->assertOk()
        ->assertSee($expectedReturnUrl)
        ->assertSee('name="context[category]"', false)
        ->assertSee('value="'.(int) $records['category']->id.'"', false);

    $this->actingAs($records['user'])
        ->withSession(['branch_id' => (int) $records['branch']->id])
        ->post(route('admin.menu.categories.store'), menuContextReturnCategoryPayload('New Root', context: $context))
        ->assertRedirect($expectedReturnUrl)
        ->assertSessionHas('status', __('menu.flash.category_created'));

    $editUrl = route('admin.menu.categories.edit', [
        'category' => (int) $records['category']->id,
        'context' => $context,
    ]);

    $this->actingAs($records['user'])
        ->withSession(['branch_id' => (int) $records['branch']->id])
        ->get($editUrl)
        ->assertOk()
        ->assertSee($expectedReturnUrl)
        ->assertSee('name="context[item_page]"', false)
        ->assertSee('value="2"', false);

    $this->actingAs($records['user'])
        ->withSession(['branch_id' => (int) $records['branch']->id])
        ->put(
            route('admin.menu.categories.update', ['category' => (int) $records['category']->id]),
            menuContextReturnCategoryPayload('Updated Breakfast', parentId: (int) $records['root']->id, context: $context),
        )
        ->assertRedirect($expectedReturnUrl)
        ->assertSessionHas('status', __('menu.flash.category_updated'));
});

it('returns item create and edit save and cancel paths to the originating menu context', function (): void {
    $records = menuContextReturnRecords(superadmin: true);
    $context = menuContextReturnQuery((int) $records['category']->id, archiveMode: 'all');
    $expectedReturnUrl = menuContextReturnUrl((int) $records['category']->id, archiveMode: 'all');

    $this->actingAs($records['user'])
        ->withSession(['branch_id' => (int) $records['branch']->id])
        ->get(route('admin.menu.items.create', ['context' => $context]))
        ->assertOk()
        ->assertSee($expectedReturnUrl)
        ->assertSee('name="category_id"', false)
        ->assertSee('value="'.(int) $records['category']->id.'"', false);

    app(TenantResolver::class)->set((int) $records['tenant']->id);
    app(BranchContext::class)->set((int) $records['branch']->id);

    Livewire::actingAs($records['user'])
        ->test(MenuItemForm::class, [
            'defaultCurrency' => 'AMD',
            'item' => null,
            'menuContext' => $context,
        ])
        ->set('category_id', (int) $records['category']->id)
        ->set('name_hy', 'Թեստային դիրք')
        ->set('name_ru', 'Тестовая позиция')
        ->set('name_en', 'Test item')
        ->set('price_major', '1200')
        ->set('currency', 'AMD')
        ->set('sort_order', 30)
        ->set('active', true)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect($expectedReturnUrl);

    $item = MenuItem::query()
        ->where('translated_name->en', 'Test item')
        ->firstOrFail();

    Livewire::actingAs($records['user'])
        ->test(MenuItemForm::class, [
            'defaultCurrency' => 'AMD',
            'item' => $item,
            'menuContext' => $context,
        ])
        ->set('name_hy', 'Թարմացված դիրք')
        ->set('name_ru', 'Обновленная позиция')
        ->set('name_en', 'Updated item')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect($expectedReturnUrl);
});

it('keeps menu context intact after validation failures', function (): void {
    $records = menuContextReturnRecords(superadmin: true);
    $context = menuContextReturnQuery((int) $records['category']->id, archiveMode: 'all');
    $expectedReturnUrl = menuContextReturnUrl((int) $records['category']->id, archiveMode: 'all');
    $createUrl = route('admin.menu.categories.create', ['context' => $context]);

    $this->actingAs($records['user'])
        ->withSession(['branch_id' => (int) $records['branch']->id])
        ->from($createUrl)
        ->followingRedirects()
        ->post(route('admin.menu.categories.store'), menuContextReturnCategoryPayload('', context: $context))
        ->assertOk()
        ->assertSee($expectedReturnUrl)
        ->assertSee('name="context[archive_mode]"', false)
        ->assertSee('value="all"', false);

    app(TenantResolver::class)->set((int) $records['tenant']->id);
    app(BranchContext::class)->set((int) $records['branch']->id);

    Livewire::actingAs($records['user'])
        ->test(MenuItemForm::class, [
            'defaultCurrency' => 'AMD',
            'item' => null,
            'menuContext' => $context,
        ])
        ->set('category_id', (int) $records['category']->id)
        ->set('price_major', '1200')
        ->set('currency', 'AMD')
        ->call('save')
        ->assertHasErrors(['name_hy'])
        ->assertSee($expectedReturnUrl);
});

it('degrades invalid bookmarked menu context category ids to the default category without leaking them', function (): void {
    $records = menuContextReturnRecords();
    $foreignRecords = menuContextReturnRecords('foreign-context', superadmin: false);

    app(TenantResolver::class)->set((int) $records['tenant']->id);
    app(BranchContext::class)->set((int) $records['branch']->id);

    $archivedRoot = app(CreateMenuCategory::class)(menuContextReturnText('Archived Root'), sortOrder: 20);
    $archivedCategory = app(CreateMenuCategory::class)(menuContextReturnText('Archived Category'), parentId: (int) $archivedRoot->id);
    app(ArchiveMenuCategory::class)((int) $archivedCategory->id);
    $nonexistentCategoryId = (int) MenuCategory::withTrashed()->max('id') + 500;

    app(TenantResolver::class)->clear();
    app(BranchContext::class)->clear();

    $expectedReturnUrl = menuContextReturnUrl((int) $records['category']->id);

    foreach ([(int) $foreignRecords['category']->id, (int) $archivedCategory->id, $nonexistentCategoryId] as $invalidCategoryId) {
        $this->actingAs($records['user'])
            ->withSession(['branch_id' => (int) $records['branch']->id])
            ->get(route('admin.menu.categories.create', [
                'context' => menuContextReturnQuery($invalidCategoryId),
            ]))
            ->assertOk()
            ->assertSee($expectedReturnUrl)
            ->assertDontSee('value="'.$invalidCategoryId.'"', false)
            ->assertDontSee('category='.$invalidCategoryId, false);
    }
});

/**
 * @return array{tenant: Tenant, branch: Branch, user: User, root: MenuCategory, category: MenuCategory, item: MenuItem}
 */
function menuContextReturnRecords(string $slug = 'context-return', bool $superadmin = false): array
{
    $tenant = Tenant::query()->create([
        'name' => str($slug)->headline()->toString(),
        'slug' => $slug,
        'default_locale' => 'hy',
        'currency' => 'AMD',
        'status' => 'active',
    ]);

    app(TenantResolver::class)->set((int) $tenant->id);

    $branch = Branch::query()->create([
        'name' => "{$slug} Branch",
        'timezone' => 'Asia/Yerevan',
        'status' => 'active',
    ]);

    app(BranchContext::class)->set((int) $branch->id);

    $root = app(CreateMenuCategory::class)(menuContextReturnText('Menu'), sortOrder: 0);
    $category = app(CreateMenuCategory::class)(menuContextReturnText('Breakfast'), sortOrder: 10, parentId: (int) $root->id);
    $item = app(CreateMenuItem::class)(
        (int) $category->id,
        menuContextReturnText('Original item'),
        null,
        new Money(100000, 'AMD'),
    );

    $role = Role::query()->create([
        'code' => "{$slug}-role",
        'name' => "{$slug} Role",
    ]);

    $permissions = collect(['menu.categories.manage', 'menu.items.manage'])
        ->map(fn (string $code): Permission => Permission::query()->firstOrCreate(
            ['code' => $code],
            ['name' => $code],
        ));

    $role->permissions()->attach(
        $permissions->pluck('id')->all(),
        ['tenant_id' => (int) $tenant->id],
    );

    $user = User::query()->create([
        'role_id' => (int) $role->id,
        'name' => "{$slug} Manager",
        'email' => "{$slug}@smartrest.test",
        'username' => "{$slug}-manager",
        'default_locale' => 'hy',
        'active' => true,
        'is_superadmin' => $superadmin,
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
        'root' => $root,
        'category' => $category,
        'item' => $item,
    ];
}

/**
 * @return array{category: int, item_page: int, q: string, archive_mode: string}
 */
function menuContextReturnQuery(int $categoryId, string $archiveMode = 'active'): array
{
    return [
        'category' => $categoryId,
        'item_page' => 2,
        'q' => 'needle',
        'archive_mode' => $archiveMode,
    ];
}

function menuContextReturnUrl(int $categoryId, string $archiveMode = 'active'): string
{
    $query = [
        'category' => $categoryId,
        'q' => 'needle',
        'item_page' => 2,
    ];

    if ($archiveMode !== 'active') {
        $query['archive_mode'] = $archiveMode;
    }

    return route('admin.menu.index', $query);
}

/**
 * @param  array<string, mixed>  $context
 * @return array<string, mixed>
 */
function menuContextReturnCategoryPayload(string $name, int $parentId = 0, array $context = []): array
{
    return [
        'context' => $context,
        'parent_id' => $parentId,
        'name_hy' => $name,
        'name_ru' => $name,
        'name_en' => $name,
        'sort_order' => 10,
        'active' => '1',
    ];
}

function menuContextReturnText(string $text): LocalizedText
{
    return LocalizedText::fromArray([
        'hy' => $text,
        'ru' => $text,
        'en' => $text,
    ]);
}
