<?php

declare(strict_types=1);

use App\Livewire\Admin\MenuItemForm;
use App\Modules\Identity\Infrastructure\Models\Permission;
use App\Modules\Identity\Infrastructure\Models\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Identity\Infrastructure\Models\UserBranchAssignment;
use App\Modules\Menu\Application\CreateMenuCategory;
use App\Modules\Menu\Application\CreateMenuItem;
use App\Modules\Menu\Application\ReplaceMenuItemImage;
use App\Modules\Menu\Domain\MenuItemImageSlot;
use App\Modules\Menu\Infrastructure\Models\MenuCategory;
use App\Modules\Menu\Infrastructure\Models\MenuItem;
use App\Modules\Menu\Infrastructure\Storage\MenuItemImageUrlResolver;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Modules\Tenancy\Infrastructure\Models\Branch;
use App\Modules\Tenancy\Infrastructure\Models\Tenant;
use App\Support\I18n\LocalizedText;
use App\Support\Money\Money;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

afterEach(function (): void {
    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();
});

it('creates a menu item with internal and public images through the Livewire form', function (): void {
    Storage::fake('public');

    $records = menuImageLivewireRecords();

    Livewire::test(MenuItemForm::class, [
        'categories' => $records['categories'],
        'defaultCurrency' => 'AMD',
        'item' => null,
    ])
        ->set('category_id', (int) $records['category']->id)
        ->set('name_hy', 'Ձվածեղ')
        ->set('name_ru', 'Омлет')
        ->set('name_en', 'Omelette')
        ->set('description_hy', '')
        ->set('description_ru', '')
        ->set('description_en', '')
        ->set('price_major', '2200')
        ->set('currency', 'AMD')
        ->set('sort_order', 10)
        ->set('active', true)
        ->set('internalUpload', UploadedFile::fake()->image('staff.jpg', 640, 420)->size(256))
        ->set('publicUpload', UploadedFile::fake()->image('guest.png', 640, 420)->size(256))
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.menu.index'));

    $item = MenuItem::query()->firstOrFail();
    $internalImage = menuImageLivewireMetadata($item, 'internal_image');
    $publicImage = menuImageLivewireMetadata($item, 'public_image');

    Storage::disk('public')->assertExists($internalImage['path']);
    Storage::disk('public')->assertExists($internalImage['thumbnail_path']);
    Storage::disk('public')->assertExists($publicImage['path']);
    Storage::disk('public')->assertExists($publicImage['thumbnail_path']);
});

it('validates Livewire image type and size before saving the form', function (): void {
    Storage::fake('public');

    $records = menuImageLivewireRecords();

    Livewire::test(MenuItemForm::class, [
        'categories' => $records['categories'],
        'defaultCurrency' => 'AMD',
        'item' => null,
    ])
        ->set('category_id', (int) $records['category']->id)
        ->set('name_hy', 'Ձվածեղ')
        ->set('name_ru', 'Омлет')
        ->set('name_en', 'Omelette')
        ->set('price_major', '2200')
        ->set('currency', 'AMD')
        ->set('sort_order', 10)
        ->set('publicUpload', UploadedFile::fake()->create('guest.pdf', 16, 'application/pdf'))
        ->call('save')
        ->assertHasErrors(['publicUpload']);

    Livewire::test(MenuItemForm::class, [
        'categories' => $records['categories'],
        'defaultCurrency' => 'AMD',
        'item' => null,
    ])
        ->set('category_id', (int) $records['category']->id)
        ->set('name_hy', 'Ձվածեղ')
        ->set('name_ru', 'Омлет')
        ->set('name_en', 'Omelette')
        ->set('price_major', '2200')
        ->set('currency', 'AMD')
        ->set('sort_order', 10)
        ->set('internalUpload', UploadedFile::fake()->image('staff.jpg', 100, 100)->size(4097))
        ->call('save')
        ->assertHasErrors(['internalUpload']);

    expect(MenuItem::query()->count())->toBe(0);
});

it('removes an existing item image through the Livewire form', function (): void {
    Storage::fake('public');

    $records = menuImageLivewireRecords();
    $item = app(CreateMenuItem::class)(
        (int) $records['category']->id,
        menuImageLivewireText('Omelette'),
        null,
        new Money(220000, 'AMD'),
    );
    $item = app(ReplaceMenuItemImage::class)(
        (int) $item->id,
        MenuItemImageSlot::Internal,
        UploadedFile::fake()->image('staff.jpg', 640, 420)->size(256),
    );
    $internalImage = menuImageLivewireMetadata($item, 'internal_image');

    Livewire::test(MenuItemForm::class, [
        'categories' => $records['categories'],
        'defaultCurrency' => 'AMD',
        'item' => $item,
    ])
        ->call('removeInternalImage')
        ->assertHasNoErrors();

    Storage::disk('public')->assertMissing($internalImage['path']);
    Storage::disk('public')->assertMissing($internalImage['thumbnail_path']);
    expect(MenuItem::query()->findOrFail((int) $item->id)->internal_image)->toBeNull();
});

it('renders the menu item form image zones and list placeholder thumbnail', function (): void {
    $records = menuImageLivewireRecords();
    $user = menuImageLivewireManager((int) $records['tenant']->id, (int) $records['branch']->id);

    $this->actingAs($user)
        ->withSession(['branch_id' => (int) $records['branch']->id])
        ->get(route('admin.menu.items.create'))
        ->assertOk()
        ->assertSee(__('menu.images.internal_title'), false)
        ->assertSee(__('menu.images.public_title'), false)
        ->assertSee('menu-item-placeholder.svg', false);

    app(CreateMenuItem::class)(
        (int) $records['category']->id,
        menuImageLivewireText('No image item'),
        null,
        new Money(220000, 'AMD'),
    );

    $this->actingAs($user)
        ->withSession(['branch_id' => (int) $records['branch']->id])
        ->get(route('admin.menu.index'))
        ->assertOk()
        ->assertSee(__('menu.images.list_thumbnail_alt'), false)
        ->assertSee('menu-item-placeholder.svg', false);
});

it('renders local public storage image URLs relative to the current host', function (): void {
    config(['filesystems.disks.public.url' => '/storage']);

    $item = new MenuItem([
        'internal_image' => [
            'path' => 'tenants/1/menu/items/9/internal/original.png',
            'thumbnail_path' => 'tenants/1/menu/items/9/internal/thumb.png',
        ],
    ]);

    expect(app(MenuItemImageUrlResolver::class)->thumbnailUrl($item, MenuItemImageSlot::Internal))
        ->toBe('/storage/tenants/1/menu/items/9/internal/thumb.png');
});

/**
 * @return array{tenant: Tenant, branch: Branch, category: MenuCategory, categories: Collection<int, MenuCategory>}
 */
function menuImageLivewireRecords(): array
{
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

    $category = app(CreateMenuCategory::class)(menuImageLivewireText('Breakfast'));

    return [
        'tenant' => $tenant,
        'branch' => $branch,
        'category' => $category,
        'categories' => $category->newCollection([$category]),
    ];
}

function menuImageLivewireText(string $text): LocalizedText
{
    return LocalizedText::fromArray([
        'hy' => $text,
        'ru' => $text,
        'en' => $text,
    ]);
}

function menuImageLivewireManager(int $tenantId, int $branchId): User
{
    app(TenantResolver::class)->set($tenantId);

    $role = Role::query()->create([
        'code' => 'menu-image-manager',
        'name' => 'Menu image manager',
    ]);

    $permissions = collect(['menu.items.manage'])
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
        'name' => 'Menu Image Manager',
        'email' => 'menu-images@tenant-a.test',
        'username' => 'menu-images',
        'default_locale' => 'hy',
        'active' => true,
        'password' => Hash::make('password'),
    ]);

    UserBranchAssignment::query()->create([
        'user_id' => (int) $user->id,
        'branch_id' => $branchId,
    ]);

    return $user;
}

/**
 * @return array{path: string, thumbnail_path: string, mime_type: string, width: int, height: int, size: int}
 */
function menuImageLivewireMetadata(MenuItem $item, string $column): array
{
    $metadata = $item->getAttribute($column);

    expect($metadata)->toBeArray();

    /** @var array{path: string, thumbnail_path: string, mime_type: string, width: int, height: int, size: int} $metadata */
    return $metadata;
}
