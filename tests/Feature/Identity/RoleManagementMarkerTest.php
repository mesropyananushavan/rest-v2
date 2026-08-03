<?php

declare(strict_types=1);

use App\Modules\Identity\Contracts\Authorizer;
use App\Modules\Identity\Infrastructure\Models\Permission;
use App\Modules\Identity\Infrastructure\Models\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Modules\Tenancy\Infrastructure\Models\Tenant;
use Database\Seeders\DemoSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

afterEach(function (): void {
    app(TenantResolver::class)->clear();
});

it('creates roles with a non nullable management bootstrap marker defaulting false', function (): void {
    expect(Schema::hasColumn('roles', 'is_management_role'))->toBeTrue();

    $tenantId = roleMarkerTenant('role-marker-schema');
    $roleId = DB::table('roles')->insertGetId([
        'tenant_id' => $tenantId,
        'code' => 'service-lead',
        'name' => 'Service Lead',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $role = Role::query()->findOrFail($roleId);

    expect($role->is_management_role)->toBeFalse();

    expect(fn (): int => DB::transaction(fn (): int => DB::table('roles')->insertGetId([
        'tenant_id' => $tenantId,
        'code' => 'null-management',
        'name' => 'Null Management',
        'is_management_role' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ])))->toThrow(QueryException::class);

    expect(DB::table('roles')->where('tenant_id', $tenantId)->where('code', 'null-management')->exists())->toBeFalse();

    $role->update(['is_management_role' => true]);

    expect($role->refresh()->is_management_role)->toBeTrue();
});

it('seeds demo management markers deterministically', function (): void {
    $this->seed(DemoSeeder::class);
    $this->seed(DemoSeeder::class);

    $roles = Role::withoutGlobalScopes()
        ->join('tenants', 'tenants.id', '=', 'roles.tenant_id')
        ->whereIn('tenants.slug', ['arat-riverside', 'northstar-bistro'])
        ->orderBy('tenants.slug')
        ->orderBy('roles.code')
        ->get(['tenants.slug as tenant_slug', 'roles.code', 'roles.is_management_role'])
        ->mapWithKeys(fn (Role $role): array => [
            "{$role->getAttribute('tenant_slug')}:{$role->getAttribute('code')}" => (bool) $role->getAttribute('is_management_role'),
        ])
        ->all();

    expect($roles)->toBe([
        'arat-riverside:cashier' => false,
        'arat-riverside:manager' => true,
        'arat-riverside:owner' => true,
        'arat-riverside:waiter' => false,
        'northstar-bistro:cashier' => false,
        'northstar-bistro:manager' => true,
        'northstar-bistro:owner' => true,
        'northstar-bistro:waiter' => false,
    ]);
});

it('keeps runtime authorization permission-only even for classified roles', function (): void {
    $tenantId = roleMarkerTenant('role-marker-runtime');
    app(TenantResolver::class)->set($tenantId);

    $classifiedRole = Role::query()->create([
        'code' => 'any-code',
        'name' => 'Any Code',
        'is_management_role' => true,
    ]);
    $user = roleMarkerUser($classifiedRole, 'classified-user');

    expect(app(Authorizer::class)->allows($user, 'orders.cancel'))->toBeFalse();

    $permission = Permission::query()->create([
        'code' => 'orders.cancel',
        'name' => 'Cancel orders',
    ]);
    $classifiedRole->permissions()->attach((int) $permission->id, [
        'tenant_id' => $tenantId,
    ]);

    expect(app(Authorizer::class)->allows($user->refresh(), 'orders.cancel'))->toBeTrue();

    $classifiedRole->permissions()->detach((int) $permission->id);

    expect(app(Authorizer::class)->allows($user->refresh(), 'orders.cancel'))->toBeFalse();

    $unclassifiedRole = Role::query()->create([
        'code' => 'owner',
        'name' => 'Owner',
        'is_management_role' => false,
    ]);
    $unclassifiedRole->permissions()->attach((int) $permission->id, [
        'tenant_id' => $tenantId,
    ]);
    $unclassifiedUser = roleMarkerUser($unclassifiedRole, 'unclassified-user');

    expect(app(Authorizer::class)->allows($unclassifiedUser, 'orders.cancel'))->toBeTrue();
});

function roleMarkerTenant(string $slug): int
{
    $tenant = Tenant::query()->create([
        'name' => str($slug)->headline()->toString(),
        'slug' => $slug,
        'default_locale' => 'en',
        'currency' => 'AMD',
        'status' => 'active',
    ]);

    app(TenantResolver::class)->set((int) $tenant->id);

    return (int) $tenant->id;
}

function roleMarkerUser(Role $role, string $username): User
{
    return User::query()->create([
        'role_id' => (int) $role->id,
        'name' => str($username)->headline()->toString(),
        'email' => "{$username}@smartrest.test",
        'username' => $username,
        'default_locale' => 'en',
        'active' => true,
        'is_superadmin' => false,
        'password' => 'password',
    ]);
}
