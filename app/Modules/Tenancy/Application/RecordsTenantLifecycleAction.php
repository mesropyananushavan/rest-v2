<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Application;

use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Modules\Tenancy\Domain\TenancyDomainException;
use App\Modules\Tenancy\Infrastructure\Models\Tenant;
use App\Modules\Tenancy\Infrastructure\Models\TenantSubscription;
use App\Support\Audit\AuditRecorder;
use App\Support\Logging\LogContext;
use App\Support\Logging\Redactor;
use Closure;
use DateTimeInterface;
use Illuminate\Support\Facades\Log;

trait RecordsTenantLifecycleAction
{
    /**
     * @param  array<string, mixed>  $context
     */
    private function logSuccess(string $action, float $startedAt, array $context = []): void
    {
        LogContext::refreshRuntimeContext('tenancy');

        Log::info('action performed', Redactor::context([
            'action' => $action,
            'duration_ms' => $this->durationMs($startedAt),
        ] + $context));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function logDomainFailure(string $action, TenancyDomainException $exception, float $startedAt, array $context = []): void
    {
        LogContext::refreshRuntimeContext('tenancy');

        Log::warning('action failed', Redactor::context([
            'action' => $action,
            'error_code' => $exception->errorCode(),
            'duration_ms' => $this->durationMs($startedAt),
        ] + $context));
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    private function auditTenantMutation(string $action, string $targetType, int $targetId, ?array $before, ?array $after): void
    {
        LogContext::refreshRuntimeContext('tenancy');

        app(AuditRecorder::class)->record($action, $targetType, $targetId, $before, $after);
    }

    /**
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    private function withTenantAuditContext(int $tenantId, Closure $callback): mixed
    {
        $tenants = app(TenantResolver::class);
        $branches = app(BranchContext::class);
        $previousContext = LogContext::current();

        $tenants->set($tenantId);
        $branches->clear();

        if (! is_string($previousContext['request_id']) || $previousContext['request_id'] === '') {
            LogContext::start(module: 'tenancy');
        } else {
            LogContext::refreshRuntimeContext('tenancy');
        }

        try {
            return $callback();
        } finally {
            $tenants->set($previousContext['tenant_id']);
            $branches->set($previousContext['branch_id']);
            LogContext::restore($previousContext);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function tenantAuditPayload(Tenant $tenant): array
    {
        return [
            'id' => (int) $tenant->id,
            'name' => (string) $tenant->name,
            'slug' => (string) $tenant->slug,
            'default_locale' => (string) $tenant->default_locale,
            'currency' => (string) $tenant->currency,
            'status' => (string) $tenant->status,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function subscriptionAuditPayload(TenantSubscription $subscription): array
    {
        return [
            'id' => (int) $subscription->id,
            'tenant_id' => (int) $subscription->tenant_id,
            'billing_anchor_day' => (int) $subscription->billing_anchor_day,
            'next_due_on' => $this->dateAuditValue($subscription->next_due_on),
            'grace_days' => (int) $subscription->grace_days,
            'last_paid_on' => $this->dateAuditValue($subscription->last_paid_on),
        ];
    }

    private function durationMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }

    private function dateAuditValue(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return is_string($value) && $value !== '' ? $value : null;
    }
}
