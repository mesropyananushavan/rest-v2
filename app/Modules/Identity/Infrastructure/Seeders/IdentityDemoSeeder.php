<?php

namespace App\Modules\Identity\Infrastructure\Seeders;

use App\Modules\Identity\Infrastructure\Models\Permission;
use App\Modules\Identity\Infrastructure\Models\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Identity\Infrastructure\Models\UserBranchAssignment;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

final class IdentityDemoSeeder extends Seeder
{
    /**
     * @param  array{tenants: array<string, int>, branches: array<string, int>}  $demo
     */
    public function seed(array $demo): void
    {
        foreach ($this->tenantUsers() as $tenantSlug => $tenantConfig) {
            $tenantId = $demo['tenants'][$tenantSlug];

            app(TenantResolver::class)->set($tenantId);

            $permissions = $this->seedPermissions();
            $roles = $this->seedRoles($tenantId, $permissions);

            foreach ($tenantConfig['users'] as $userRow) {
                $user = User::query()->updateOrCreate(
                    [
                        'tenant_id' => $tenantId,
                        'username' => $userRow['username'],
                    ],
                    [
                        'role_id' => (int) $roles[$userRow['role']]->id,
                        'name' => $userRow['name'],
                        'email' => $userRow['email'],
                        'default_locale' => $userRow['locale'],
                        'active' => true,
                        'password' => Hash::make($userRow['password']),
                    ],
                );

                foreach ($userRow['branches'] as $branchKey) {
                    UserBranchAssignment::query()->updateOrCreate([
                        'tenant_id' => $tenantId,
                        'user_id' => (int) $user->id,
                        'branch_id' => $demo['branches'][$branchKey],
                    ]);
                }
            }
        }

        app(BranchContext::class)->clear();
        app(TenantResolver::class)->clear();
    }

    /**
     * @return array<string, Permission>
     */
    private function seedPermissions(): array
    {
        $permissions = [];

        foreach ($this->permissionRows() as $code => $name) {
            $permissions[$code] = Permission::query()->updateOrCreate(
                ['code' => $code],
                ['name' => $name],
            );
        }

        return $permissions;
    }

    /**
     * @param  array<string, Permission>  $permissions
     * @return array<string, Role>
     */
    private function seedRoles(int $tenantId, array $permissions): array
    {
        $roles = [];

        foreach ($this->rolePermissions() as $roleCode => $permissionCodes) {
            $role = Role::query()->updateOrCreate(
                ['code' => $roleCode],
                ['name' => ucfirst($roleCode)],
            );

            $roles[$roleCode] = $role;

            $role->permissions()->syncWithPivotValues(
                collect($permissionCodes)
                    ->map(fn (string $code): int => (int) $permissions[$code]->id)
                    ->all(),
                ['tenant_id' => $tenantId],
            );
        }

        return $roles;
    }

    /**
     * @return array<string, string>
     */
    private function permissionRows(): array
    {
        return [
            'tenancy.manage' => 'Manage tenants and branches',
            'identity.manage' => 'Manage users and roles',
            'menu.items.manage' => 'Manage menu items',
            'orders.take' => 'Take orders',
            'payments.capture' => 'Capture payments',
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private function rolePermissions(): array
    {
        return [
            'owner' => ['tenancy.manage', 'identity.manage', 'menu.items.manage', 'orders.take', 'payments.capture'],
            'manager' => ['identity.manage', 'menu.items.manage', 'orders.take', 'payments.capture'],
            'cashier' => ['orders.take', 'payments.capture'],
            'waiter' => ['orders.take'],
        ];
    }

    /**
     * @return array<string, array{users: list<array{name: string, username: string, email: string, role: string, locale: string, password: string, branches: list<string>}>}>
     */
    private function tenantUsers(): array
    {
        return [
            'arat-riverside' => [
                'users' => [
                    ['name' => 'Ani Petrosyan', 'username' => 'arat-owner', 'email' => 'owner@arat.test', 'role' => 'owner', 'locale' => 'hy', 'password' => 'password', 'branches' => ['arat-kentron', 'arat-dilijan']],
                    ['name' => 'Gor Hakobyan', 'username' => 'arat-manager', 'email' => 'manager@arat.test', 'role' => 'manager', 'locale' => 'hy', 'password' => 'password', 'branches' => ['arat-kentron', 'arat-dilijan']],
                    ['name' => 'Mariam Sargsyan', 'username' => 'arat-cashier', 'email' => 'cashier@arat.test', 'role' => 'cashier', 'locale' => 'hy', 'password' => 'password', 'branches' => ['arat-kentron']],
                    ['name' => 'Tigran Manukyan', 'username' => 'arat-waiter', 'email' => 'waiter@arat.test', 'role' => 'waiter', 'locale' => 'hy', 'password' => 'password', 'branches' => ['arat-dilijan']],
                ],
            ],
            'northstar-bistro' => [
                'users' => [
                    ['name' => 'Olivia Carter', 'username' => 'northstar-owner', 'email' => 'owner@northstar.test', 'role' => 'owner', 'locale' => 'en', 'password' => 'password', 'branches' => ['northstar-downtown']],
                    ['name' => 'Noah Bennett', 'username' => 'northstar-manager', 'email' => 'manager@northstar.test', 'role' => 'manager', 'locale' => 'en', 'password' => 'password', 'branches' => ['northstar-downtown']],
                    ['name' => 'Emma Brooks', 'username' => 'northstar-cashier', 'email' => 'cashier@northstar.test', 'role' => 'cashier', 'locale' => 'en', 'password' => 'password', 'branches' => ['northstar-downtown']],
                    ['name' => 'Liam Reed', 'username' => 'northstar-waiter', 'email' => 'waiter@northstar.test', 'role' => 'waiter', 'locale' => 'en', 'password' => 'password', 'branches' => ['northstar-downtown']],
                ],
            ],
        ];
    }
}
