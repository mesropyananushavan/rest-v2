<?php

declare(strict_types=1);

use App\Livewire\Admin\DashboardCounters;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Identity\Infrastructure\Models\UserBranchAssignment;
use App\Modules\Menu\Infrastructure\Models\MenuCategory;
use App\Modules\Menu\Infrastructure\Models\MenuItem;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Modules\Tenancy\Infrastructure\Models\Branch;
use App\Modules\Tenancy\Infrastructure\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

afterEach(function (): void {
    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();
});

it('redirects guests from the admin dashboard to login', function (): void {
    $this->get(route('admin.dashboard'))
        ->assertRedirect(route('login'));
});

it('renders the SmartRest admin shell and current tenant dashboard counters', function (): void {
    $record = adminDashboardUser();

    app(TenantResolver::class)->set((int) $record['tenant']->id);
    app(BranchContext::class)->set((int) $record['branch']->id);

    $rootCategory = MenuCategory::query()->create([
        'translated_name' => adminDashboardText('Menu'),
        'sort_order' => 0,
        'active' => true,
    ]);

    $firstCategory = MenuCategory::query()->create([
        'parent_id' => (int) $rootCategory->id,
        'translated_name' => adminDashboardText('Breakfast'),
        'sort_order' => 1,
        'active' => true,
    ]);

    $secondCategory = MenuCategory::query()->create([
        'parent_id' => (int) $rootCategory->id,
        'translated_name' => adminDashboardText('Lunch'),
        'sort_order' => 2,
        'active' => true,
    ]);

    foreach ([$firstCategory, $firstCategory, $secondCategory] as $index => $category) {
        MenuItem::query()->create([
            'branch_id' => (int) $record['branch']->id,
            'category_id' => (int) $category->id,
            'translated_name' => adminDashboardText("Item {$index}"),
            'translated_description' => null,
            'price_minor' => 100000,
            'currency' => 'AMD',
            'sort_order' => $index,
            'active' => true,
        ]);
    }

    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();

    $this->actingAs($record['user'])
        ->withSession(['branch_id' => (int) $record['branch']->id])
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee(__('admin.brand.name'), false)
        ->assertSee(__('admin.nav.dashboard'), false)
        ->assertSee(__('admin.nav.menu'), false)
        ->assertSee($record['tenant']->name, false)
        ->assertSee($record['branch']->name, false)
        ->assertSee($record['user']->name, false)
        ->assertSee(__('admin.dashboard.metrics.categories.label'), false)
        ->assertSee(__('admin.dashboard.metrics.items.label'), false)
        ->assertSeeLivewire(DashboardCounters::class)
        ->assertSee('3', false);
});

it('loads dashboard counters through the Livewire component', function (): void {
    $record = adminDashboardUser();

    app(TenantResolver::class)->set((int) $record['tenant']->id);
    app(BranchContext::class)->set((int) $record['branch']->id);

    $rootCategory = MenuCategory::query()->create([
        'translated_name' => adminDashboardText('Menu'),
        'sort_order' => 0,
        'active' => true,
    ]);

    $category = MenuCategory::query()->create([
        'parent_id' => (int) $rootCategory->id,
        'translated_name' => adminDashboardText('Breakfast'),
        'sort_order' => 1,
        'active' => true,
    ]);

    foreach (range(1, 2) as $index) {
        MenuItem::query()->create([
            'branch_id' => (int) $record['branch']->id,
            'category_id' => (int) $category->id,
            'translated_name' => adminDashboardText("Item {$index}"),
            'translated_description' => null,
            'price_minor' => 100000,
            'currency' => 'AMD',
            'sort_order' => $index,
            'active' => true,
        ]);
    }

    Livewire::actingAs($record['user'])
        ->test(DashboardCounters::class)
        ->assertSet('categoryCount', 2)
        ->assertSet('itemCount', 2)
        ->assertSee(__('admin.dashboard.metrics.categories.label'), false)
        ->assertSee(__('admin.dashboard.metrics.items.label'), false);
});

/**
 * @return array{tenant: Tenant, branch: Branch, user: User}
 */
function adminDashboardUser(): array
{
    $tenant = Tenant::query()->create([
        'name' => 'Arat Hospitality',
        'slug' => 'arat-admin-dashboard',
        'default_locale' => 'en',
        'currency' => 'AMD',
        'status' => 'active',
    ]);

    app(TenantResolver::class)->set((int) $tenant->id);

    $branch = Branch::query()->create([
        'name' => 'Kentron Branch',
        'timezone' => 'Asia/Yerevan',
        'status' => 'active',
    ]);

    app(BranchContext::class)->set((int) $branch->id);

    $user = User::query()->create([
        'name' => 'Admin Manager',
        'email' => 'admin-dashboard@smartrest.test',
        'username' => 'admin-dashboard',
        'default_locale' => 'en',
        'active' => true,
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
    ];
}

/**
 * @return array{hy: string, ru: string, en: string}
 */
function adminDashboardText(string $text): array
{
    return [
        'hy' => $text,
        'ru' => $text,
        'en' => $text,
    ];
}
