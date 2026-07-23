<?php

declare(strict_types=1);

namespace App\Modules\Tables\Application;

use App\Modules\Tables\Domain\TablesDomainException;
use App\Modules\Tables\Infrastructure\Models\Hall;
use App\Support\Audit\AuditRecorder;
use App\Support\Logging\LogContext;
use App\Support\Logging\Redactor;
use DateTimeInterface;
use Illuminate\Support\Facades\Log;

trait RecordsTableAction
{
    /**
     * @param  array<string, mixed>  $context
     */
    private function logSuccess(string $action, float $startedAt, array $context = []): void
    {
        LogContext::refreshRuntimeContext('tables');

        Log::info('action performed', Redactor::context([
            'action' => $action,
            'duration_ms' => $this->durationMs($startedAt),
        ] + $context));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function logDomainFailure(string $action, TablesDomainException $exception, float $startedAt, array $context = []): void
    {
        LogContext::refreshRuntimeContext('tables');

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
    private function auditTableMutation(string $action, string $targetType, int $targetId, ?array $before, ?array $after): void
    {
        LogContext::refreshRuntimeContext('tables');

        app(AuditRecorder::class)->record($action, $targetType, $targetId, $before, $after);
    }

    /**
     * @return array<string, mixed>
     */
    private function hallAuditPayload(Hall $hall): array
    {
        return [
            'id' => (int) $hall->id,
            'branch_id' => (int) $hall->branch_id,
            'translated_name' => $hall->getAttribute('translated_name'),
            'color' => (string) $hall->color,
            'sort_order' => (int) $hall->sort_order,
            'active' => (bool) $hall->active,
            'deleted_at' => $this->dateAuditValue($hall->deleted_at),
        ];
    }

    private function durationMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }

    private function dateAuditValue(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }

        return is_string($value) && $value !== '' ? $value : null;
    }
}
