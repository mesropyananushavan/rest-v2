<?php

declare(strict_types=1);

namespace App\Support\I18n\Application;

use App\Support\Audit\AuditRecorder;
use App\Support\I18n\TenantTranslationOverride;
use App\Support\I18n\TenantTranslationOverrideException;
use App\Support\Logging\LogContext;
use App\Support\Logging\Redactor;
use Illuminate\Support\Facades\Log;

trait RecordsTenantTranslationOverrideAction
{
    /**
     * @param  array<string, mixed>  $context
     */
    private function logSuccess(string $action, float $startedAt, array $context = []): void
    {
        LogContext::refreshRuntimeContext('i18n');

        Log::info('action performed', Redactor::context([
            'action' => $action,
            'duration_ms' => $this->durationMs($startedAt),
        ] + $context));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function logDomainFailure(string $action, TenantTranslationOverrideException $exception, float $startedAt, array $context = []): void
    {
        LogContext::refreshRuntimeContext('i18n');

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
    private function auditTranslationOverrideMutation(string $action, int $targetId, ?array $before, ?array $after): void
    {
        LogContext::refreshRuntimeContext('i18n');

        app(AuditRecorder::class)->record($action, 'tenant_translation_override', $targetId, $before, $after);
    }

    /**
     * @return array<string, mixed>
     */
    private function translationOverrideAuditPayload(TenantTranslationOverride $override): array
    {
        return [
            'id' => (int) $override->id,
            'locale' => (string) $override->locale,
            'translation_key' => (string) $override->translation_key,
            'override_value' => (string) $override->override_value,
        ];
    }

    private function durationMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
