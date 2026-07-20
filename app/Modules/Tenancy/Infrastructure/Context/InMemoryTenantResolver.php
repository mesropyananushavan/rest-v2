<?php

namespace App\Modules\Tenancy\Infrastructure\Context;

use App\Modules\Tenancy\Contracts\TenantResolver;
use Illuminate\Support\Facades\DB;

final class InMemoryTenantResolver implements TenantResolver
{
    private ?int $tenantId = null;

    public function id(): ?int
    {
        return $this->tenantId;
    }

    public function set(?int $tenantId): void
    {
        $this->tenantId = $tenantId;
        $this->syncPostgresSetting('smartrest.tenant_id', $tenantId);
    }

    public function clear(): void
    {
        $this->set(null);
    }

    private function syncPostgresSetting(string $key, ?int $value): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('select set_config(?, ?, false)', [$key, $value === null ? '' : (string) $value]);
    }
}
