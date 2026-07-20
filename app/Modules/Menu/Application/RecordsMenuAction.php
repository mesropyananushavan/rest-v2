<?php

declare(strict_types=1);

namespace App\Modules\Menu\Application;

use App\Modules\Menu\Domain\MenuDomainException;
use App\Support\Logging\LogContext;
use App\Support\Logging\Redactor;
use Illuminate\Support\Facades\Log;

trait RecordsMenuAction
{
    /**
     * @param  array<string, mixed>  $context
     */
    private function logSuccess(string $action, float $startedAt, array $context = []): void
    {
        LogContext::refreshRuntimeContext('menu');

        Log::info('action performed', Redactor::context([
            'action' => $action,
            'duration_ms' => $this->durationMs($startedAt),
        ] + $context));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function logDomainFailure(string $action, MenuDomainException $exception, float $startedAt, array $context = []): void
    {
        LogContext::refreshRuntimeContext('menu');

        Log::warning('action failed', Redactor::context([
            'action' => $action,
            'error_code' => $exception->errorCode(),
            'duration_ms' => $this->durationMs($startedAt),
        ] + $context));
    }

    private function durationMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
