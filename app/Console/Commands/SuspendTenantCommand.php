<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Tenancy\Application\SuspendTenant;
use App\Modules\Tenancy\Domain\TenancyDomainException;
use App\Modules\Tenancy\Infrastructure\Models\Tenant;
use Illuminate\Console\Command;

final class SuspendTenantCommand extends Command
{
    protected $signature = 'tenancy:tenant:suspend
        {tenant_id : Tenant id}';

    protected $description = 'Manually suspend a tenant.';

    public function handle(SuspendTenant $suspendTenant): int
    {
        $tenantId = $this->tenantIdArgument();
        $tenant = Tenant::query()->find($tenantId);

        if (! $tenant instanceof Tenant) {
            return $this->domainFailure(TenancyDomainException::unknownTenant());
        }

        $this->line(sprintf('Tenant: #%d %s (%s)', $tenantId, (string) $tenant->name, (string) $tenant->slug));
        $this->line('Current status: '.(string) $tenant->status);

        if (! $this->confirm('Suspend this tenant?', false)) {
            $this->warn('Tenant suspension cancelled.');

            return self::SUCCESS;
        }

        try {
            $tenant = $suspendTenant($tenantId);
        } catch (TenancyDomainException $exception) {
            return $this->domainFailure($exception);
        }

        $this->info(sprintf('Tenant suspended: tenant_id=%d status=%s.', $tenantId, (string) $tenant->status));

        return self::SUCCESS;
    }

    private function tenantIdArgument(): int
    {
        $tenantId = filter_var($this->argument('tenant_id'), FILTER_VALIDATE_INT);

        return is_int($tenantId) ? $tenantId : 0;
    }

    private function domainFailure(TenancyDomainException $exception): int
    {
        $this->error('Domain failure: '.$exception->errorCode());

        return self::FAILURE;
    }
}
