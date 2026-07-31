<?php

declare(strict_types=1);

use App\Modules\Identity\Contracts\BranchAssignableUser;
use App\Modules\Identity\Contracts\UserDirectory;
use App\Modules\Identity\Infrastructure\Models\Permission;
use App\Modules\Identity\Infrastructure\Models\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Identity\Infrastructure\Models\UserBranchAssignment;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Modules\Tenancy\Infrastructure\Models\Branch;
use App\Modules\Tenancy\Infrastructure\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

afterEach(function (): void {
    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();
});

it('lists active branch assigned users with a permission without leaking other tenants or branches', function (): void {
    $tenantA = userDirectoryTenant('tenant-a');
    $tenantB = userDirectoryTenant('tenant-b');

    app(TenantResolver::class)->set((int) $tenantA['tenant']->id);

    $waiterRole = userDirectoryRole('waiter', ['orders.take']);
    $viewerRole = userDirectoryRole('viewer', []);
    $zara = userDirectoryUser($waiterRole, 'Zara Active', 'zara', active: true);
    $aram = userDirectoryUser($waiterRole, 'Aram Active', 'aram', active: true);
    $breakGlass = userDirectoryUser($viewerRole, 'Break Glass', 'break-glass', active: true, superadmin: true);
    $wrongBranch = userDirectoryUser($waiterRole, 'Wrong Branch', 'wrong-branch', active: true);
    $inactive = userDirectoryUser($waiterRole, 'Inactive Staff', 'inactive', active: false);
    $inactiveSuperadmin = userDirectoryUser($viewerRole, 'Inactive Break Glass', 'inactive-break-glass', active: false, superadmin: true);
    $noPermission = userDirectoryUser($viewerRole, 'No Permission', 'no-permission', active: true);

    userDirectoryAssign($aram, $tenantA['branches'][0]);
    userDirectoryAssign($breakGlass, $tenantA['branches'][0]);
    userDirectoryAssign($zara, $tenantA['branches'][0]);
    userDirectoryAssign($wrongBranch, $tenantA['branches'][1]);
    userDirectoryAssign($inactive, $tenantA['branches'][0]);
    userDirectoryAssign($inactiveSuperadmin, $tenantA['branches'][0]);
    userDirectoryAssign($noPermission, $tenantA['branches'][0]);

    app(TenantResolver::class)->set((int) $tenantB['tenant']->id);

    $foreignRole = userDirectoryRole('waiter', ['orders.take']);
    $foreign = userDirectoryUser($foreignRole, 'Foreign Waiter', 'foreign', active: true);
    userDirectoryAssign($foreign, $tenantB['branches'][0]);

    app(TenantResolver::class)->set((int) $tenantA['tenant']->id);

    $users = app(UserDirectory::class)
        ->activeUsersAssignedToBranchWithPermission((int) $tenantA['branches'][0]->id, 'orders.take');

    expect($users)->toHaveCount(3)
        ->sequence(
            fn ($user) => $user
                ->toBeInstanceOf(BranchAssignableUser::class)
                ->id->toBe((int) $aram->id)
                ->displayName->toBe('Aram Active'),
            fn ($user) => $user
                ->toBeInstanceOf(BranchAssignableUser::class)
                ->id->toBe((int) $breakGlass->id)
                ->displayName->toBe('Break Glass'),
            fn ($user) => $user
                ->toBeInstanceOf(BranchAssignableUser::class)
                ->id->toBe((int) $zara->id)
                ->displayName->toBe('Zara Active'),
        );

    $directory = app(UserDirectory::class);

    foreach ([$aram, $breakGlass, $zara] as $allowed) {
        expect($directory->isActiveUserAssignedToBranchWithPermission((int) $allowed->id, (int) $tenantA['branches'][0]->id, 'orders.take'))
            ->toBeTrue();
    }

    foreach ([$wrongBranch, $inactive, $inactiveSuperadmin, $noPermission, $foreign] as $rejected) {
        expect($directory->isActiveUserAssignedToBranchWithPermission((int) $rejected->id, (int) $tenantA['branches'][0]->id, 'orders.take'))
            ->toBeFalse();
    }
});

it('creates the branch staff lookup index for permission-filtered assignment lists', function (): void {
    $indexes = collect(Schema::getIndexes('user_branch_assignments'))
        ->pluck('name')
        ->all();

    expect($indexes)->toContain('user_branch_assignments_tenant_branch_user_idx');
});

/**
 * @return array{tenant: Tenant, branches: list<Branch>}
 */
function userDirectoryTenant(string $slug): array
{
    $tenant = Tenant::query()->create([
        'name' => str($slug)->headline()->toString(),
        'slug' => $slug,
        'default_locale' => 'en',
        'currency' => 'AMD',
        'status' => 'active',
    ]);

    app(TenantResolver::class)->set((int) $tenant->id);

    $branches = [
        Branch::query()->create([
            'name' => "{$slug} Branch 1",
            'timezone' => 'Asia/Yerevan',
            'status' => 'active',
        ]),
        Branch::query()->create([
            'name' => "{$slug} Branch 2",
            'timezone' => 'Asia/Yerevan',
            'status' => 'active',
        ]),
    ];

    return [
        'tenant' => $tenant,
        'branches' => $branches,
    ];
}

/**
 * @param  list<string>  $permissionCodes
 */
function userDirectoryRole(string $code, array $permissionCodes): Role
{
    $role = Role::query()->create([
        'code' => $code,
        'name' => str($code)->headline()->toString(),
    ]);

    $permissions = collect($permissionCodes)
        ->map(fn (string $permissionCode): Permission => Permission::query()->create([
            'code' => $permissionCode,
            'name' => $permissionCode,
        ]));

    if ($permissions->isNotEmpty()) {
        $role->permissions()->attach(
            $permissions->pluck('id')->all(),
            ['tenant_id' => (int) $role->tenant_id],
        );
    }

    return $role;
}

function userDirectoryUser(Role $role, string $name, string $username, bool $active, bool $superadmin = false): User
{
    return User::query()->create([
        'role_id' => (int) $role->id,
        'name' => $name,
        'email' => "{$username}@smartrest.test",
        'username' => $username,
        'default_locale' => 'en',
        'active' => $active,
        'is_superadmin' => $superadmin,
        'password' => Hash::make('password'),
    ]);
}

function userDirectoryAssign(User $user, Branch $branch): void
{
    UserBranchAssignment::query()->create([
        'user_id' => (int) $user->id,
        'branch_id' => (int) $branch->id,
    ]);
}
