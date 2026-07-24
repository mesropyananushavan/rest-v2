<?php

declare(strict_types=1);

use App\Modules\Identity\Contracts\Authorizer;
use App\Modules\Identity\Infrastructure\Models\Permission;
use App\Modules\Identity\Infrastructure\Models\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Modules\Tenancy\Infrastructure\Models\Tenant;
use App\Support\I18n\TenantTranslationOverridePermissions;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

afterEach(function (): void {
    app(TenantResolver::class)->clear();
});

it('denies tenant translation override management without the dedicated permission', function (): void {
    $user = i18nPermissionUser('translation-denied', []);

    app(TenantResolver::class)->set((int) $user->tenant_id);

    expect(app(Authorizer::class)->allows($user, TenantTranslationOverridePermissions::MANAGE))->toBeFalse()
        ->and(Gate::forUser($user)->allows(TenantTranslationOverridePermissions::MANAGE))->toBeFalse();
});

it('allows tenant translation override management with the dedicated permission', function (): void {
    $user = i18nPermissionUser('translation-granted', [TenantTranslationOverridePermissions::MANAGE]);

    app(TenantResolver::class)->set((int) $user->tenant_id);

    expect(app(Authorizer::class)->allows($user, TenantTranslationOverridePermissions::MANAGE))->toBeTrue()
        ->and(Gate::forUser($user)->allows(TenantTranslationOverridePermissions::MANAGE))->toBeTrue();
});

it('allows active superadmins without an explicit tenant translation override permission grant', function (): void {
    $user = i18nPermissionUser('translation-superadmin', [], superadmin: true);

    app(TenantResolver::class)->set((int) $user->tenant_id);

    expect(app(Authorizer::class)->allows($user, TenantTranslationOverridePermissions::MANAGE))->toBeTrue()
        ->and(Gate::forUser($user)->allows(TenantTranslationOverridePermissions::MANAGE))->toBeTrue();
});

it('grants tenant translation override management in demo tenants', function (): void {
    Storage::fake('public');

    $this->seed(DemoSeeder::class);

    app(TenantResolver::class)->set((int) User::withoutGlobalScopes()->where('email', 'manager@arat.test')->firstOrFail()->tenant_id);

    $aratManager = User::withoutGlobalScopes()->where('email', 'manager@arat.test')->firstOrFail();

    expect(app(Authorizer::class)->allows($aratManager, TenantTranslationOverridePermissions::MANAGE))->toBeTrue();

    app(TenantResolver::class)->set((int) User::withoutGlobalScopes()->where('email', 'manager@northstar.test')->firstOrFail()->tenant_id);

    $northstarManager = User::withoutGlobalScopes()->where('email', 'manager@northstar.test')->firstOrFail();

    expect(app(Authorizer::class)->allows($northstarManager, TenantTranslationOverridePermissions::MANAGE))->toBeTrue();
});

/**
 * @param  list<string>  $permissionCodes
 */
function i18nPermissionUser(string $slug, array $permissionCodes, bool $superadmin = false): User
{
    $tenant = Tenant::query()->create([
        'name' => str($slug)->headline()->toString(),
        'slug' => $slug,
        'default_locale' => 'hy',
        'currency' => 'AMD',
        'status' => 'active',
    ]);

    app(TenantResolver::class)->set((int) $tenant->id);

    $role = Role::query()->create([
        'code' => "{$slug}-manager",
        'name' => "{$slug} Manager",
    ]);

    $permissions = collect($permissionCodes)
        ->map(fn (string $code): Permission => Permission::query()->create([
            'code' => $code,
            'name' => $code,
        ]));

    if ($permissions->isNotEmpty()) {
        $role->permissions()->attach(
            $permissions->pluck('id')->all(),
            ['tenant_id' => (int) $tenant->id],
        );
    }

    $user = User::query()->create([
        'role_id' => (int) $role->id,
        'name' => str($slug)->headline()->toString(),
        'email' => "{$slug}@smartrest.test",
        'username' => $slug,
        'default_locale' => 'hy',
        'active' => true,
        'is_superadmin' => $superadmin,
        'password' => Hash::make('password'),
    ]);

    app(TenantResolver::class)->clear();

    return $user;
}
