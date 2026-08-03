<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('backfills orders cancel permission only to classified management roles idempotently', function (): void {
    $tenantId = DB::table('tenants')->insertGetId([
        'name' => 'Legacy Tenant',
        'slug' => 'legacy-tenant',
        'default_locale' => 'en',
        'currency' => 'AMD',
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $roleIds = [];

    $roleClassifications = [
        'owner' => false,
        'manager' => false,
        'custom-operator' => true,
        'cashier' => false,
        'waiter' => false,
    ];

    foreach ($roleClassifications as $roleCode => $isManagementRole) {
        $roleIds[$roleCode] = DB::table('roles')->insertGetId([
            'tenant_id' => (int) $tenantId,
            'code' => $roleCode,
            'name' => str($roleCode)->headline()->toString(),
            'is_management_role' => $isManagementRole,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    expect(DB::table('permissions')->where('tenant_id', (int) $tenantId)->where('code', 'orders.cancel')->exists())->toBeFalse();

    $migration = ordersCancelPermissionBackfillMigration();
    $migration->up();
    $migration->up();

    $permissionIds = DB::table('permissions')
        ->where('tenant_id', (int) $tenantId)
        ->where('code', 'orders.cancel')
        ->pluck('id');

    expect($permissionIds)->toHaveCount(1);

    $permissionId = (int) $permissionIds->first();

    expect(ordersCancelBackfillRoleHasPermission((int) $roleIds['custom-operator'], $permissionId))->toBeTrue();

    foreach (['owner', 'manager', 'cashier', 'waiter'] as $roleCode) {
        expect(ordersCancelBackfillRoleHasPermission((int) $roleIds[$roleCode], $permissionId))->toBeFalse();
    }

    expect(DB::table('role_permissions')->where('permission_id', $permissionId)->count())->toBe(1);

    $migration->down();

    expect(DB::table('permissions')->where('id', $permissionId)->exists())->toBeTrue()
        ->and(ordersCancelBackfillRoleHasPermission((int) $roleIds['custom-operator'], $permissionId))->toBeTrue();
});

function ordersCancelPermissionBackfillMigration(): Migration
{
    return require database_path('migrations/2026_08_03_000000_backfill_orders_cancel_permission.php');
}

function ordersCancelBackfillRoleHasPermission(int $roleId, int $permissionId): bool
{
    return DB::table('role_permissions')
        ->where('role_id', $roleId)
        ->where('permission_id', $permissionId)
        ->exists();
}
