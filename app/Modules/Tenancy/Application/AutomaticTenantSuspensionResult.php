<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Application;

use DateTimeImmutable;

final readonly class AutomaticTenantSuspensionResult
{
    public function __construct(
        public DateTimeImmutable $evaluatedAt,
        public bool $quietHourReached,
        public int $candidateCount,
        public int $suspendedCount,
        public int $skippedNotServiceableCount,
        public int $skippedNoLongerSuspendableCount,
        public int $skippedAlreadySuspendedCount,
        public int $skippedUnknownTenantCount,
    ) {}
}
