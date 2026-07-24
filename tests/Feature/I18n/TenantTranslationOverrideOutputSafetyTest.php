<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Models\Permission;
use App\Modules\Identity\Infrastructure\Models\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Identity\Infrastructure\Models\UserBranchAssignment;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Modules\Tenancy\Infrastructure\Models\Branch;
use App\Modules\Tenancy\Infrastructure\Models\Tenant;
use App\Support\I18n\Application\SetTenantTranslationOverride;
use App\Support\I18n\TenantTranslationOverridePermissions;
use App\Support\I18n\TenantTranslationOverrides;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

afterEach(function (): void {
    Cache::flush();
    app(TenantTranslationOverrides::class)->clearRequestCache();
    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();
    app()->setLocale('en');
});

it('renders markup in a tenant translation override as escaped text', function (): void {
    $user = i18nOutputSafetyUser('translation-output-safety');
    $unsafe = '<script>alert("x")</script><strong>Injected</strong>';

    $this->actingAs($user);
    app(TenantResolver::class)->set((int) $user->tenant_id);

    app(SetTenantTranslationOverride::class)($user, 'en', 'admin.dashboard.title', $unsafe);

    app(TenantResolver::class)->clear();
    app(BranchContext::class)->clear();

    $this->get(route('admin.dashboard'))
        ->assertOk()
        ->assertDontSee($unsafe, false)
        ->assertSee('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;&lt;strong&gt;Injected&lt;/strong&gt;', false);
});

function i18nOutputSafetyUser(string $slug): User
{
    $tenant = Tenant::query()->create([
        'name' => str($slug)->headline()->toString(),
        'slug' => $slug,
        'default_locale' => 'en',
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

    $role = Role::query()->create([
        'code' => "{$slug}-manager",
        'name' => "{$slug} Manager",
    ]);

    $permission = Permission::query()->create([
        'code' => TenantTranslationOverridePermissions::MANAGE,
        'name' => TenantTranslationOverridePermissions::MANAGE,
    ]);

    $role->permissions()->attach((int) $permission->id, ['tenant_id' => (int) $tenant->id]);

    $user = User::query()->create([
        'role_id' => (int) $role->id,
        'name' => str($slug)->headline()->toString(),
        'email' => "{$slug}@smartrest.test",
        'username' => $slug,
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

    return $user;
}
