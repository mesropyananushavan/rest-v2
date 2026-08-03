<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('backfills orders cancel permission only to existing owner and manager roles idempotently', function (): void {
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

    foreach (['owner', 'manager', 'cashier', 'waiter'] as $roleCode) {
        $roleIds[$roleCode] = DB::table('roles')->insertGetId([
            'tenant_id' => (int) $tenantId,
            'code' => $roleCode,
            'name' => str($roleCode)->headline()->toString(),
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

    foreach (['owner', 'manager'] as $roleCode) {
        expect(ordersCancelBackfillRoleHasPermission((int) $roleIds[$roleCode], $permissionId))->toBeTrue();
    }

    foreach (['cashier', 'waiter'] as $roleCode) {
        expect(ordersCancelBackfillRoleHasPermission((int) $roleIds[$roleCode], $permissionId))->toBeFalse();
    }

    expect(DB::table('role_permissions')->where('permission_id', $permissionId)->count())->toBe(2);

    $migration->down();

    expect(DB::table('permissions')->where('id', $permissionId)->exists())->toBeTrue()
        ->and(ordersCancelBackfillRoleHasPermission((int) $roleIds['owner'], $permissionId))->toBeTrue()
        ->and(ordersCancelBackfillRoleHasPermission((int) $roleIds['manager'], $permissionId))->toBeTrue();
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
