<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Application;

use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantDirectory;
use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Modules\Tenancy\Contracts\TenantSubscriptionReader;
use App\Modules\Tenancy\Domain\TenancyDomainException;
use App\Modules\Tenancy\Infrastructure\Models\Tenant;
use App\Support\Logging\LogContext;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use UnexpectedValueException;

final class SuspendOverdueTenantSubscriptions
{
    use RecordsTenantLifecycleAction;

    public function __construct(
        private readonly TenantSubscriptionReader $subscriptions,
        private readonly TenantDirectory $tenants,
        private readonly SuspendTenant $suspendTenant,
        private readonly TenantResolver $tenantResolver,
        private readonly BranchContext $branches,
    ) {}

    public function __invoke(?DateTimeInterface $now = null): AutomaticTenantSuspensionResult
    {
        $startedAt = microtime(true);
        $previousTenantId = $this->tenantResolver->id();
        $previousBranchId = $this->branches->id();
        $previousLogContext = LogContext::current();

        $this->tenantResolver->clear();
        $this->branches->clear();
        LogContext::start(module: 'tenancy');

        try {
            $evaluatedAt = $this->now($now);

            if (! $this->quietHourReached($evaluatedAt)) {
                $result = new AutomaticTenantSuspensionResult(
                    evaluatedAt: $evaluatedAt,
                    quietHourReached: false,
                    candidateCount: 0,
                    suspendedCount: 0,
                    skippedNotServiceableCount: 0,
                    skippedNoLongerSuspendableCount: 0,
                    skippedAlreadySuspendedCount: 0,
                    skippedUnknownTenantCount: 0,
                );

                $this->logResult($startedAt, $result);

                return $result;
            }

            $tenantIds = $this->subscriptions->suspendableTenantIds($evaluatedAt);
            $suspendedCount = 0;
            $skippedNotServiceableCount = 0;
            $skippedNoLongerSuspendableCount = 0;
            $skippedAlreadySuspendedCount = 0;
            $skippedUnknownTenantCount = 0;

            foreach ($tenantIds as $tenantId) {
                match ($this->suspendTenantIfStillEligible($tenantId, $evaluatedAt, $startedAt)) {
                    'suspended' => $suspendedCount++,
                    'not_serviceable' => $skippedNotServiceableCount++,
                    'no_longer_suspendable' => $skippedNoLongerSuspendableCount++,
                    'already_suspended' => $skippedAlreadySuspendedCount++,
                    'unknown_tenant' => $skippedUnknownTenantCount++,
                };
            }

            $result = new AutomaticTenantSuspensionResult(
                evaluatedAt: $evaluatedAt,
                quietHourReached: true,
                candidateCount: count($tenantIds),
                suspendedCount: $suspendedCount,
                skippedNotServiceableCount: $skippedNotServiceableCount,
                skippedNoLongerSuspendableCount: $skippedNoLongerSuspendableCount,
                skippedAlreadySuspendedCount: $skippedAlreadySuspendedCount,
                skippedUnknownTenantCount: $skippedUnknownTenantCount,
            );

            $this->logResult($startedAt, $result);

            return $result;
        } finally {
            $this->tenantResolver->set($previousTenantId);
            $this->branches->set($previousBranchId);
            LogContext::restore($previousLogContext);
        }
    }

    /**
     * @return 'suspended'|'not_serviceable'|'no_longer_suspendable'|'already_suspended'|'unknown_tenant'
     */
    private function suspendTenantIfStillEligible(int $tenantId, DateTimeImmutable $evaluatedAt, float $startedAt): string
    {
        return DB::transaction(function () use ($evaluatedAt, $startedAt, $tenantId): string {
            $tenant = Tenant::query()
                ->whereKey($tenantId)
                ->lockForUpdate()
                ->first();

            if (! $tenant instanceof Tenant) {
                return 'unknown_tenant';
            }

            if (! $this->tenants->isServiceable($tenantId)) {
                return 'not_serviceable';
            }

            $status = $this->subscriptions->statusForTenant($tenantId, $evaluatedAt);

            if ($status?->isSuspendable !== true) {
                return 'no_longer_suspendable';
            }

            try {
                ($this->suspendTenant)($tenantId);

                return 'suspended';
            } catch (TenancyDomainException $exception) {
                if ($exception->errorCode() === 'tenancy.tenant_already_suspended') {
                    return 'already_suspended';
                }

                if ($exception->errorCode() === 'tenancy.unknown_tenant') {
                    return 'unknown_tenant';
                }

                $this->logDomainFailure('tenancy.subscriptions.auto_suspend', $exception, $startedAt, [
                    'tenant_id' => $tenantId,
                ]);

                throw $exception;
            }
        });
    }

    private function now(?DateTimeInterface $now): DateTimeImmutable
    {
        if ($now !== null) {
            return DateTimeImmutable::createFromInterface($now)->setTimezone($this->timezone());
        }

        return new DateTimeImmutable('now', $this->timezone());
    }

    private function quietHourReached(DateTimeImmutable $now): bool
    {
        [$hour, $minute] = $this->quietHour();

        return $now >= $now->setTime($hour, $minute);
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function quietHour(): array
    {
        $quietHour = config('billing.automatic_suspension.quiet_hour');

        if (! is_string($quietHour) || ! preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $quietHour, $matches)) {
            throw new UnexpectedValueException('Billing automatic suspension quiet hour must use HH:MM format.');
        }

        return [(int) $matches[1], (int) $matches[2]];
    }

    private function timezone(): DateTimeZone
    {
        $timezone = config('billing.platform_timezone');

        if (! is_string($timezone) || $timezone === '') {
            throw new UnexpectedValueException('Billing platform timezone must be configured.');
        }

        return new DateTimeZone($timezone);
    }

    private function logResult(float $startedAt, AutomaticTenantSuspensionResult $result): void
    {
        $this->logSuccess('tenancy.subscriptions.auto_suspend', $startedAt, [
            'evaluated_at' => $result->evaluatedAt->format(DateTimeInterface::ATOM),
            'quiet_hour_reached' => $result->quietHourReached,
            'candidate_count' => $result->candidateCount,
            'suspended_count' => $result->suspendedCount,
            'skipped_not_serviceable_count' => $result->skippedNotServiceableCount,
            'skipped_no_longer_suspendable_count' => $result->skippedNoLongerSuspendableCount,
            'skipped_already_suspended_count' => $result->skippedAlreadySuspendedCount,
            'skipped_unknown_tenant_count' => $result->skippedUnknownTenantCount,
        ]);
    }
}
