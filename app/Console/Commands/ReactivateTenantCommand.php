<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Tenancy\Application\ReactivateTenant;
use App\Modules\Tenancy\Contracts\TenantSubscriptionReader;
use App\Modules\Tenancy\Domain\TenancyDomainException;
use App\Modules\Tenancy\Infrastructure\Models\Tenant;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Console\Command;

final class ReactivateTenantCommand extends Command
{
    protected $signature = 'tenancy:tenant:reactivate
        {tenant_id : Tenant id}';

    protected $description = 'Reactivate a manually suspended tenant.';

    public function __construct(
        private readonly TenantSubscriptionReader $subscriptions,
    ) {
        parent::__construct();
    }

    public function handle(ReactivateTenant $reactivateTenant): int
    {
        $tenantId = $this->tenantIdArgument();
        $tenant = Tenant::query()->find($tenantId);

        if (! $tenant instanceof Tenant) {
            return $this->domainFailure(TenancyDomainException::unknownTenant());
        }

        $this->line(sprintf('Tenant: #%d %s (%s)', $tenantId, (string) $tenant->name, (string) $tenant->slug));
        $this->line('Current status: '.(string) $tenant->status);

        $subscriptionStatus = $this->subscriptions->statusForTenant($tenantId, $this->now());

        if ($subscriptionStatus?->isSuspendable === true) {
            $this->warn('WARNING: this tenant is still suspendable. The automated suspension job will suspend it again unless the subscription is advanced or intentionally forgiven.');
        }

        if (! $this->confirm('Reactivate this tenant?', false)) {
            $this->warn('Tenant reactivation cancelled.');

            return self::SUCCESS;
        }

        try {
            $tenant = $reactivateTenant($tenantId);
        } catch (TenancyDomainException $exception) {
            return $this->domainFailure($exception);
        }

        $this->info(sprintf('Tenant reactivated: tenant_id=%d status=%s.', $tenantId, (string) $tenant->status));

        return self::SUCCESS;
    }

    private function tenantIdArgument(): int
    {
        $tenantId = filter_var($this->argument('tenant_id'), FILTER_VALIDATE_INT);

        return is_int($tenantId) ? $tenantId : 0;
    }

    private function now(): DateTimeImmutable
    {
        $timezone = config('billing.platform_timezone');
        assert(is_string($timezone));

        return new DateTimeImmutable('now', new DateTimeZone($timezone));
    }

    private function domainFailure(TenancyDomainException $exception): int
    {
        $this->error('Domain failure: '.$exception->errorCode());

        return self::FAILURE;
    }
}
