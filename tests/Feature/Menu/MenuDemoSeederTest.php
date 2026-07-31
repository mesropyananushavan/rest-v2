<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Menu\Infrastructure\Models\MenuItem;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

afterEach(function (): void {
    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();
});

it('seeds deterministic menu data visible to demo managers by tenant', function (): void {
    Storage::fake('public');

    $this->seed(DemoSeeder::class);

    expect(User::withoutGlobalScopes()->where('is_superadmin', true)->pluck('email')->all())
        ->toBe([]);

    $aratOwner = User::withoutGlobalScopes()->where('email', 'owner@arat.test')->firstOrFail();
    $aratManager = User::withoutGlobalScopes()->where('email', 'manager@arat.test')->firstOrFail();
    $aratCashier = User::withoutGlobalScopes()->where('email', 'cashier@arat.test')->firstOrFail();
    $aratWaiter = User::withoutGlobalScopes()->where('email', 'waiter@arat.test')->firstOrFail();

    app(TenantResolver::class)->set((int) $aratOwner->tenant_id);

    $archivePermissionCodes = [
        'menu.archive.view',
        'menu.categories.restore',
        'menu.categories.force_delete',
        'menu.items.restore',
        'menu.items.force_delete',
        'tables.halls.archive.view',
        'tables.halls.restore',
        'tables.halls.force_delete',
        'tables.tables.archive.view',
        'tables.tables.restore',
        'tables.tables.force_delete',
    ];

    $roleArchivePermissions = [
        'owner' => demoRolePermissionIntersection($aratOwner, $archivePermissionCodes),
        'manager' => demoRolePermissionIntersection($aratManager, $archivePermissionCodes),
        'cashier' => demoRolePermissionIntersection($aratCashier, $archivePermissionCodes),
        'waiter' => demoRolePermissionIntersection($aratWaiter, $archivePermissionCodes),
    ];

    expect($roleArchivePermissions)->toBe([
        'owner' => $archivePermissionCodes,
        'manager' => [
            'menu.archive.view',
            'menu.categories.restore',
            'menu.items.restore',
            'tables.halls.archive.view',
            'tables.halls.restore',
            'tables.tables.archive.view',
            'tables.tables.restore',
        ],
        'cashier' => [],
        'waiter' => [],
    ]);

    expect($roleArchivePermissions['manager'])
        ->toContain('menu.categories.restore')
        ->toContain('menu.items.restore')
        ->toContain('tables.halls.restore')
        ->toContain('tables.tables.restore')
        ->not->toContain('menu.categories.force_delete')
        ->not->toContain('menu.items.force_delete')
        ->not->toContain('tables.halls.force_delete')
        ->not->toContain('tables.tables.force_delete');

    $this->withSession(['_token' => menuDemoCsrfToken()])
        ->post(route('login.store'), menuDemoLoginPayload('manager@arat.test'))
        ->assertRedirect(route('admin.dashboard'));

    $this->get(route('admin.menu.index'))
        ->assertOk()
        ->assertSee('Լոռի ձվածեղ', false)
        ->assertSee('2200 ֏', false)
        ->assertDontSee('Երեւանյան աղցան', false)
        ->assertDontSee('Northstar burger', false);

    $loriOmelette = MenuItem::query()
        ->whereJsonContains('translated_name->en', 'Lori omelette')
        ->firstOrFail();
    $yerevanSalad = MenuItem::query()
        ->whereJsonContains('translated_name->en', 'Yerevan salad')
        ->firstOrFail();
    $chickenKhorovats = MenuItem::query()
        ->whereJsonContains('translated_name->en', 'Chicken khorovats')
        ->firstOrFail();

    expectMenuDemoItemInSubcategory($loriOmelette);
    expectMenuDemoItemInSubcategory($yerevanSalad);
    expectMenuDemoItemInSubcategory($chickenKhorovats);

    $this->get(route('admin.menu.index', ['category' => (int) $yerevanSalad->category_id]))
        ->assertOk()
        ->assertSee('Երեւանյան աղցան', false)
        ->assertSee('2600 ֏', false)
        ->assertDontSee('Northstar burger', false);

    $loriInternalImage = menuDemoImageMetadata($loriOmelette, 'internal_image');
    $yerevanPublicImage = menuDemoImageMetadata($yerevanSalad, 'public_image');

    Storage::disk('public')->assertExists($loriInternalImage['path']);
    Storage::disk('public')->assertExists($loriInternalImage['thumbnail_path']);
    Storage::disk('public')->assertExists($yerevanPublicImage['path']);
    Storage::disk('public')->assertExists($yerevanPublicImage['thumbnail_path']);
    expect($loriOmelette->public_image)->toBeNull()
        ->and($yerevanSalad->internal_image)->toBeNull()
        ->and($chickenKhorovats->internal_image)->toBeNull()
        ->and($chickenKhorovats->public_image)->toBeNull();

    $this->withSession(['_token' => menuDemoCsrfToken()])
        ->post(route('logout'), ['_token' => menuDemoCsrfToken()])
        ->assertRedirect('/');

    $this->withSession(['_token' => menuDemoCsrfToken()])
        ->post(route('login.store'), menuDemoLoginPayload('manager@northstar.test'))
        ->assertRedirect(route('admin.dashboard'));

    $this->get(route('admin.menu.index'))
        ->assertOk()
        ->assertSee('Corn chowder', false)
        ->assertSee('$7.99', false)
        ->assertDontSee('Northstar burger', false)
        ->assertDontSee('Լոռի ձվածեղ', false);

    $northstarBurger = MenuItem::query()
        ->whereJsonContains('translated_name->en', 'Northstar burger')
        ->firstOrFail();

    expectMenuDemoItemInSubcategory($northstarBurger);

    $this->get(route('admin.menu.index', ['category' => (int) $northstarBurger->category_id]))
        ->assertOk()
        ->assertSee('Northstar burger', false)
        ->assertSee('$14.99', false)
        ->assertDontSee('Լոռի ձվածեղ', false);
});

function menuDemoCsrfToken(): string
{
    return 'menu-demo-test-token';
}

/**
 * @param  list<string>  $permissionCodes
 * @return list<string>
 */
function demoRolePermissionIntersection(User $user, array $permissionCodes): array
{
    $held = $user->role()->firstOrFail()->permissions()->pluck('code')->all();

    return array_values(array_intersect($permissionCodes, $held));
}

/**
 * @return array<string, string>
 */
function menuDemoLoginPayload(string $email): array
{
    return [
        '_token' => menuDemoCsrfToken(),
        'tenant_slug' => str_contains($email, '@arat.') ? 'arat-riverside' : 'northstar-bistro',
        'email' => $email,
        'password' => 'password',
    ];
}

function expectMenuDemoItemInSubcategory(MenuItem $item): void
{
    expect($item->category()->firstOrFail()->parent_id)->not->toBeNull();
}

/**
 * @return array{path: string, thumbnail_path: string, mime_type: string, width: int, height: int, size: int}
 */
function menuDemoImageMetadata(MenuItem $item, string $column): array
{
    $metadata = $item->getAttribute($column);

    expect($metadata)->toBeArray();

    /** @var array{path: string, thumbnail_path: string, mime_type: string, width: int, height: int, size: int} $metadata */
    return $metadata;
}
