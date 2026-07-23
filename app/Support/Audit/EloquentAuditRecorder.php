<?php

declare(strict_types=1);

namespace App\Support\Audit;

use App\Support\Logging\LogContext;
use App\Support\Logging\Redactor;
use RuntimeException;
use Throwable;

final class EloquentAuditRecorder implements AuditRecorder
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function record(
        string $action,
        string $targetType,
        int $targetId,
        ?array $before = null,
        ?array $after = null,
    ): AuditLog {
        $context = LogContext::current();

        if (! is_string($context['request_id']) || $context['request_id'] === '') {
            LogContext::start(module: $context['module']);
            $context = LogContext::current();
        }

        $tenantId = $context['tenant_id'];

        if (! is_int($tenantId)) {
            throw new RuntimeException('Audit recording requires a tenant context.');
        }

        $requestId = $context['request_id'];

        if (! is_string($requestId) || $requestId === '') {
            throw new RuntimeException('Audit recording requires a correlation id.');
        }

        return AuditLog::query()->create([
            'tenant_id' => $tenantId,
            'branch_id' => $context['branch_id'],
            'actor_id' => $context['user_id'],
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'before_json' => $before === null ? null : Redactor::context($before),
            'after_json' => $after === null ? null : Redactor::context($after),
            'correlation_id' => $requestId,
            'ip_address' => $this->ipAddress(),
        ]);
    }

    private function ipAddress(): ?string
    {
        try {
            $ipAddress = request()->ip();
        } catch (Throwable) {
            return null;
        }

        return is_string($ipAddress) && $ipAddress !== '' ? $ipAddress : null;
    }
}
