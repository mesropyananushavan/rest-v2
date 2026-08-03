<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const string PERMISSION_CODE = 'orders.cancel';

    private const string PERMISSION_NAME = 'Cancel orders';

    public function up(): void
    {
        $now = now();
        $previousTenantSetting = $this->currentPostgresTenantSetting();

        try {
            foreach (DB::table('tenants')->pluck('id') as $tenantId) {
                $tenantId = (int) $tenantId;
                $this->setPostgresTenantSetting($tenantId);

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
                    ->where('is_management_role', true)
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
        } finally {
            $this->restorePostgresTenantSetting($previousTenantSetting);
        }
    }

    public function down(): void
    {
        // Data-only backfill: do not remove permissions that may already be intentional.
    }

    private function currentPostgresTenantSetting(): ?string
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return null;
        }

        $row = DB::selectOne("select current_setting('smartrest.tenant_id', true) as tenant_id");

        return (string) ($row?->tenant_id ?? '');
    }

    private function setPostgresTenantSetting(int $tenantId): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("select set_config('smartrest.tenant_id', ?, false)", [(string) $tenantId]);
    }

    private function restorePostgresTenantSetting(?string $tenantId): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("select set_config('smartrest.tenant_id', ?, false)", [$tenantId ?? '']);
    }
};
