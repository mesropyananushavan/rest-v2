<?php

declare(strict_types=1);

namespace App\Support\Audit;

interface AuditRecorder
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
    ): AuditLog;
}
