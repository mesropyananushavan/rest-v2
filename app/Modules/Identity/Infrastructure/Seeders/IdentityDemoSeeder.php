<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Seeders;

use App\Modules\Identity\Infrastructure\Models\Permission;
use App\Modules\Identity\Infrastructure\Models\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Identity\Infrastructure\Models\UserBranchAssignment;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Support\Audit\AuditLogPermissions;
use App\Support\I18n\TenantTranslationOverridePermissions;
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

            $permissions = $this->seedPermissions($tenantId);
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
                        'is_superadmin' => $userRow['superadmin'],
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
    private function seedPermissions(int $tenantId): array
    {
        $permissions = [];

        foreach ($this->permissionRows() as $code => $name) {
            $permissions[$code] = Permission::query()->updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'code' => $code,
                ],
                [
                    'name' => $name,
                ],
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
                [
                    'tenant_id' => $tenantId,
                    'code' => $roleCode,
                ],
                [
                    'name' => ucfirst($roleCode),
                ],
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
            AuditLogPermissions::VIEW => 'View audit logs',
            TenantTranslationOverridePermissions::MANAGE => 'Manage tenant translation overrides',
            'identity.manage' => 'Manage users and roles',
            'menu.archive.view' => 'View archived menu records',
            'menu.categories.manage' => 'Manage menu categories',
            'menu.categories.restore' => 'Restore archived menu categories',
            'menu.categories.force_delete' => 'Permanently delete archived menu categories',
            'menu.items.manage' => 'Manage menu items',
            'menu.items.restore' => 'Restore archived menu items',
            'menu.items.force_delete' => 'Permanently delete archived menu items',
            'tables.halls.archive.view' => 'View archived halls',
            'tables.halls.manage' => 'Manage halls',
            'tables.halls.restore' => 'Restore archived halls',
            'tables.halls.force_delete' => 'Permanently delete archived halls',
            'tables.tables.archive.view' => 'View archived tables',
            'tables.tables.manage' => 'Manage tables',
            'tables.tables.restore' => 'Restore archived tables',
            'tables.tables.force_delete' => 'Permanently delete archived tables',
            'orders.take' => 'Take orders',
            'orders.cancel' => 'Cancel orders',
            'payments.capture' => 'Capture payments',
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private function rolePermissions(): array
    {
        return [
            'owner' => ['tenancy.manage', AuditLogPermissions::VIEW, TenantTranslationOverridePermissions::MANAGE, 'identity.manage', 'menu.archive.view', 'menu.categories.manage', 'menu.categories.restore', 'menu.categories.force_delete', 'menu.items.manage', 'menu.items.restore', 'menu.items.force_delete', 'tables.halls.archive.view', 'tables.halls.manage', 'tables.halls.restore', 'tables.halls.force_delete', 'tables.tables.archive.view', 'tables.tables.manage', 'tables.tables.restore', 'tables.tables.force_delete', 'orders.take', 'orders.cancel', 'payments.capture'],
            'manager' => [TenantTranslationOverridePermissions::MANAGE, 'identity.manage', 'menu.archive.view', 'menu.categories.manage', 'menu.categories.restore', 'menu.items.manage', 'menu.items.restore', 'tables.halls.archive.view', 'tables.halls.manage', 'tables.halls.restore', 'tables.tables.archive.view', 'tables.tables.manage', 'tables.tables.restore', 'orders.take', 'orders.cancel', 'payments.capture'],
            'cashier' => ['orders.take', 'payments.capture'],
            'waiter' => ['orders.take'],
        ];
    }

    /**
     * @return array<string, array{users: list<array{name: string, username: string, email: string, role: string, locale: string, password: string, superadmin: bool, branches: list<string>}>}>
     */
    private function tenantUsers(): array
    {
        return [
            'arat-riverside' => [
                'users' => [
                    ['name' => 'Ani Petrosyan', 'username' => 'arat-owner', 'email' => 'owner@arat.test', 'role' => 'owner', 'locale' => 'hy', 'password' => 'password', 'superadmin' => false, 'branches' => ['arat-kentron', 'arat-dilijan']],
                    ['name' => 'Gor Hakobyan', 'username' => 'arat-manager', 'email' => 'manager@arat.test', 'role' => 'manager', 'locale' => 'hy', 'password' => 'password', 'superadmin' => false, 'branches' => ['arat-kentron', 'arat-dilijan']],
                    ['name' => 'Mariam Sargsyan', 'username' => 'arat-cashier', 'email' => 'cashier@arat.test', 'role' => 'cashier', 'locale' => 'hy', 'password' => 'password', 'superadmin' => false, 'branches' => ['arat-kentron']],
                    ['name' => 'Tigran Manukyan', 'username' => 'arat-waiter', 'email' => 'waiter@arat.test', 'role' => 'waiter', 'locale' => 'hy', 'password' => 'password', 'superadmin' => false, 'branches' => ['arat-dilijan']],
                ],
            ],
            'northstar-bistro' => [
                'users' => [
                    ['name' => 'Olivia Carter', 'username' => 'northstar-owner', 'email' => 'owner@northstar.test', 'role' => 'owner', 'locale' => 'en', 'password' => 'password', 'superadmin' => false, 'branches' => ['northstar-downtown']],
                    ['name' => 'Noah Bennett', 'username' => 'northstar-manager', 'email' => 'manager@northstar.test', 'role' => 'manager', 'locale' => 'en', 'password' => 'password', 'superadmin' => false, 'branches' => ['northstar-downtown']],
                    ['name' => 'Emma Brooks', 'username' => 'northstar-cashier', 'email' => 'cashier@northstar.test', 'role' => 'cashier', 'locale' => 'en', 'password' => 'password', 'superadmin' => false, 'branches' => ['northstar-downtown']],
                    ['name' => 'Liam Reed', 'username' => 'northstar-waiter', 'email' => 'waiter@northstar.test', 'role' => 'waiter', 'locale' => 'en', 'password' => 'password', 'superadmin' => false, 'branches' => ['northstar-downtown']],
                ],
            ],
        ];
    }
}
