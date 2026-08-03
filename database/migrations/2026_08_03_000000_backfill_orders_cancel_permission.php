<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const string PERMISSION_CODE = 'orders.cancel';

    private const string PERMISSION_NAME = 'Cancel orders';

    /**
     * @var list<string>
     */
    private const array MANAGING_ROLE_CODES = ['owner', 'manager'];

    public function up(): void
    {
        $now = now();

        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            $tenantId = (int) $tenantId;
            $permissionId = DB::table('permissions')
                ->where('tenant_id', $tenantId)
                ->where('code', self::PERMISSION_CODE)
                ->value('id');

            if ($permissionId === null) {
                $permissionId = DB::table('permissions')->insertGetId([
                    'tenant_id' => $tenantId,
                    'code' => self::PERMISSION_CODE,
                    'name' => self::PERMISSION_NAME,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $roleIds = DB::table('roles')
                ->where('tenant_id', $tenantId)
                ->whereIn('code', self::MANAGING_ROLE_CODES)
                ->pluck('id');

            foreach ($roleIds as $roleId) {
                $roleId = (int) $roleId;
                $alreadyAttached = DB::table('role_permissions')
                    ->where('role_id', $roleId)
                    ->where('permission_id', (int) $permissionId)
                    ->exists();

                if ($alreadyAttached) {
                    continue;
                }

                DB::table('role_permissions')->insert([
                    'tenant_id' => $tenantId,
                    'role_id' => $roleId,
                    'permission_id' => (int) $permissionId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Data-only backfill: do not remove permissions that may already be intentional.
    }
};
