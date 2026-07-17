<?php

use App\Modules\Identity\Contracts\Authorizer;
use App\Modules\Identity\Infrastructure\Models\Permission;
use App\Modules\Identity\Infrastructure\Models\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Identity\Infrastructure\Models\UserBranchAssignment;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Modules\Tenancy\Infrastructure\Models\Branch;
use App\Modules\Tenancy\Infrastructure\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

afterEach(function (): void {
    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();
});

it('prevents a user from tenant A from seeing tenant B identity or branch data', function (): void {
    $tenantA = tenantWithUser('tenant-a', 'manager-a', ['menu.items.manage']);
    $tenantB = tenantWithUser('tenant-b', 'manager-b', ['menu.items.manage']);

    app(TenantResolver::class)->set((int) $tenantA['tenant']->id);

    expect(User::query()->pluck('username')->all())->toContain('manager-a')
        ->not->toContain('manager-b')
        ->and(Branch::query()->pluck('name')->all())->toContain('tenant-a Branch')
        ->not->toContain('tenant-b Branch')
        ->and(User::query()->find((int) $tenantB['user']->id))->toBeNull()
        ->and(Branch::query()->find((int) $tenantB['branch']->id))->toBeNull()
        ->and(User::withoutGlobalScopes()->count())->toBe(2)
        ->and(Branch::withoutGlobalScopes()->count())->toBe(2);

    app(TenantResolver::class)->clear();

    expect(User::query()->count())->toBe(0)
        ->and(Branch::query()->count())->toBe(0);
});

it('prevents writes and deletes against another tenant through scoped Eloquent operations', function (): void {
    $tenantA = tenantWithUser('tenant-a', 'manager-a', ['menu.items.manage']);
    $tenantB = tenantWithUser('tenant-b', 'manager-b', ['menu.items.manage']);

    app(TenantResolver::class)->set((int) $tenantA['tenant']->id);

    $updatedByWhere = User::query()
        ->where('id', (int) $tenantB['user']->id)
        ->update(['name' => 'Compromised User']);

    $updatedByWhereKey = Branch::query()
        ->whereKey((int) $tenantB['branch']->id)
        ->update(['name' => 'Compromised Branch']);

    $deletedByWhere = User::query()
        ->where('id', (int) $tenantB['user']->id)
        ->delete();

    $deletedByWhereKey = Branch::query()
        ->whereKey((int) $tenantB['branch']->id)
        ->delete();

    expect($updatedByWhere)->toBe(0)
        ->and($updatedByWhereKey)->toBe(0)
        ->and($deletedByWhere)->toBe(0)
        ->and($deletedByWhereKey)->toBe(0)
        ->and(User::withoutGlobalScopes()->find((int) $tenantB['user']->id)?->name)->toBe('manager-b')
        ->and(Branch::withoutGlobalScopes()->find((int) $tenantB['branch']->id)?->name)->toBe('tenant-b Branch');
});

it('forces created records into the current tenant even when a foreign tenant id is supplied', function (): void {
    $tenantA = tenantWithUser('tenant-a', 'manager-a', ['menu.items.manage']);
    $tenantB = tenantWithUser('tenant-b', 'manager-b', ['menu.items.manage']);

    app(TenantResolver::class)->set((int) $tenantA['tenant']->id);

    $branch = Branch::query()->create([
        'tenant_id' => (int) $tenantB['tenant']->id,
        'name' => 'Tenant Override Attempt',
        'timezone' => 'Asia/Yerevan',
        'status' => 'active',
    ]);

    expect((int) $branch->tenant_id)->toBe((int) $tenantA['tenant']->id)
        ->and(Branch::withoutGlobalScopes()->find((int) $branch->id)?->tenant_id)->toBe($tenantA['tenant']->id);
});

it('returns 404 when an authenticated user requests another tenant resource by id', function (): void {
    $tenantA = tenantWithUser('tenant-a', 'manager-a', ['menu.items.manage']);
    $tenantB = tenantWithUser('tenant-b', 'manager-b', ['menu.items.manage']);

    $this->actingAs($tenantA['user'])
        ->get(route('admin.branches.show', ['branch' => (int) $tenantA['branch']->id]))
        ->assertOk()
        ->assertJsonPath('data.id', (int) $tenantA['branch']->id);

    $this->actingAs($tenantA['user'])
        ->get(route('admin.branches.show', ['branch' => (int) $tenantB['branch']->id]))
        ->assertNotFound();
});

it('resolves tenant and branch context from request headers', function (): void {
    $tenant = tenantWithUser('tenant-a', 'manager-a', ['menu.items.manage']);

    Route::middleware('web')->get('/_test/context', fn () => response()->json([
        'tenant_id' => app(TenantResolver::class)->id(),
        'branch_id' => app(BranchContext::class)->id(),
        'locale' => app()->getLocale(),
    ]));

    $this->withHeader('X-Tenant-ID', (string) $tenant['tenant']->id)
        ->withHeader('X-Branch-ID', (string) $tenant['branch']->id)
        ->get('/_test/context')
        ->assertOk()
        ->assertJson([
            'tenant_id' => (int) $tenant['tenant']->id,
            'branch_id' => (int) $tenant['branch']->id,
            'locale' => 'hy',
        ]);
});

it('checks action permissions through the identity authorizer contract', function (): void {
    $tenant = tenantWithUser('tenant-a', 'manager-a', ['menu.items.manage']);

    app(TenantResolver::class)->set((int) $tenant['tenant']->id);

    expect(app(Authorizer::class)->allows($tenant['user'], 'menu.items.manage'))->toBeTrue()
        ->and(app(Authorizer::class)->allows($tenant['user'], 'identity.manage'))->toBeFalse()
        ->and(Gate::forUser($tenant['user'])->allows('menu.items.manage'))->toBeTrue()
        ->and(Gate::forUser($tenant['user'])->allows('identity.manage'))->toBeFalse();
});

/**
 * @param  list<string>  $permissionCodes
 * @return array{tenant: Tenant, branch: Branch, role: Role, user: User}
 */
function tenantWithUser(string $slug, string $username, array $permissionCodes): array
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

    $role = Role::query()->create([
        'code' => "{$slug}-manager",
        'name' => "{$slug} Manager",
    ]);

    $permissions = collect($permissionCodes)
        ->map(fn (string $code): Permission => Permission::query()->create([
            'code' => $code,
            'name' => $code,
        ]));

    $role->permissions()->attach(
        $permissions->pluck('id')->all(),
        ['tenant_id' => (int) $tenant->id],
    );

    $user = User::query()->create([
        'role_id' => (int) $role->id,
        'name' => $username,
        'email' => "{$username}@smartrest.test",
        'username' => $username,
        'default_locale' => 'hy',
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
        'role' => $role,
        'user' => $user,
    ];
}
