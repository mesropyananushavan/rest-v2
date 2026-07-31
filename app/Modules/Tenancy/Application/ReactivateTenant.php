<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Application;

use App\Modules\Tenancy\Domain\TenancyDomainException;
use App\Modules\Tenancy\Infrastructure\Models\Tenant;
use Illuminate\Support\Facades\DB;

final class ReactivateTenant
{
    use RecordsTenantLifecycleAction;

    public function __invoke(int $tenantId): Tenant
    {
        $startedAt = microtime(true);

        try {
            $tenant = $this->withTenantAuditContext(
                $tenantId,
                fn (): Tenant => DB::transaction(function () use ($tenantId): Tenant {
                    $tenant = Tenant::query()
                        ->whereKey($tenantId)
                        ->lockForUpdate()
                        ->first();

                    if (! $tenant instanceof Tenant) {
                        throw TenancyDomainException::unknownTenant();
                    }

                    if ((string) $tenant->status === 'active') {
                        throw TenancyDomainException::tenantAlreadyActive();
                    }

                    $before = $this->tenantAuditPayload($tenant);

                    $tenant->forceFill([
                        'status' => 'active',
                    ])->save();

                    $tenant = $tenant->refresh();

                    $this->auditTenantMutation(
                        'tenancy.tenant.reactivated',
                        'tenant',
                        (int) $tenant->id,
                        $before,
                        $this->tenantAuditPayload($tenant),
                    );

                    return $tenant;
                }),
            );

            $this->logSuccess('tenancy.tenant.reactivate', $startedAt, [
                'tenant_id' => $tenantId,
            ]);

            return $tenant;
        } catch (TenancyDomainException $exception) {
            $this->logDomainFailure('tenancy.tenant.reactivate', $exception, $startedAt, [
                'tenant_id' => $tenantId,
            ]);

            throw $exception;
        }
    }
}
