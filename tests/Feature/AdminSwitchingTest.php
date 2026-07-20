<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Identity\Infrastructure\Models\UserBranchAssignment;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Modules\Tenancy\Infrastructure\Models\Branch;
use App\Modules\Tenancy\Infrastructure\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

afterEach(function (): void {
    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();
});

it('switches the current branch through assigned user branches without changing tenant', function (): void {
    $record = adminSwitchingUser(defaultLocale: 'en', branchNames: ['Kentron Branch', 'Arabkir Branch']);

    $this->actingAs($record['user'])
        ->withSession(['branch_id' => (int) $record['branches'][0]->id])
        ->post(route('admin.branch.switch'), [
            'branch_id' => (int) $record['branches'][1]->id,
        ])
        ->assertRedirect()
        ->assertSessionHas('tenant_id', (int) $record['tenant']->id)
        ->assertSessionHas('branch_id', (int) $record['branches'][1]->id)
        ->assertSessionHas('status', __('admin.flash.branch_updated'));

    $this->actingAs($record['user'])
        ->withSession(['branch_id' => (int) $record['branches'][1]->id])
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Arabkir Branch', false)
        ->assertDontSee('Kentron Branch</strong>', false);
});

it('rejects switching to an unassigned branch', function (): void {
    $record = adminSwitchingUser(defaultLocale: 'en', branchNames: ['Kentron Branch', 'Arabkir Branch'], assignAllBranches: false);

    $this->actingAs($record['user'])
        ->withSession(['branch_id' => (int) $record['branches'][0]->id])
        ->post(route('admin.branch.switch'), [
            'branch_id' => (int) $record['branches'][1]->id,
        ])
        ->assertNotFound()
        ->assertSessionHas('branch_id', (int) $record['branches'][0]->id);
});

it('uses tenant default locale until the user switches locale in session', function (): void {
    $record = adminSwitchingUser(defaultLocale: 'hy', branchNames: ['Kentron Branch']);

    $this->actingAs($record['user'])
        ->withSession(['branch_id' => (int) $record['branches'][0]->id])
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee(__('admin.dashboard.title'), false)
        ->assertSee('Վահանակ', false);

    $this->actingAs($record['user'])
        ->withSession(['branch_id' => (int) $record['branches'][0]->id])
        ->post(route('admin.locale.switch'), [
            'locale' => 'ru',
        ])
        ->assertRedirect()
        ->assertSessionHas('locale', 'ru')
        ->assertSessionHas('status', __('admin.flash.locale_updated'));

    $this->actingAs($record['user'])
        ->withSession([
            'branch_id' => (int) $record['branches'][0]->id,
            'locale' => 'ru',
        ])
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Панель', false);
});

/**
 * @return array{tenant: Tenant, branches: list<Branch>, user: User}
 */
function adminSwitchingUser(string $defaultLocale, array $branchNames, bool $assignAllBranches = true): array
{
    $tenant = Tenant::query()->create([
        'name' => 'Switching Tenant',
        'slug' => 'switching-tenant-'.strtolower($defaultLocale).'-'.count($branchNames).'-'.($assignAllBranches ? 'all' : 'one'),
        'default_locale' => $defaultLocale,
        'currency' => 'AMD',
        'status' => 'active',
    ]);

    app(TenantResolver::class)->set((int) $tenant->id);

    $branches = [];

    foreach ($branchNames as $branchName) {
        $branches[] = Branch::query()->create([
            'name' => $branchName,
            'timezone' => 'Asia/Yerevan',
            'status' => 'active',
        ]);
    }

    app(BranchContext::class)->set((int) $branches[0]->id);

    $user = User::query()->create([
        'name' => 'Switching Manager',
        'email' => 'switching-'.strtolower($defaultLocale).'-'.count($branchNames).'-'.($assignAllBranches ? 'all' : 'one').'@smartrest.test',
        'username' => 'switching-'.strtolower($defaultLocale).'-'.count($branchNames).'-'.($assignAllBranches ? 'all' : 'one'),
        'default_locale' => $defaultLocale,
        'active' => true,
        'password' => Hash::make('password'),
    ]);

    foreach ($branches as $index => $branch) {
        if (! $assignAllBranches && $index > 0) {
            continue;
        }

        UserBranchAssignment::query()->create([
            'user_id' => (int) $user->id,
            'branch_id' => (int) $branch->id,
        ]);
    }

    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();

    return [
        'tenant' => $tenant,
        'branches' => $branches,
        'user' => $user,
    ];
}
