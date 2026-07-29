<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Contracts;

use DateTimeImmutable;

final readonly class TenantSubscriptionStatus
{
    public function __construct(
        public int $tenantId,
        public DateTimeImmutable $nextDueOn,
        public DateTimeImmutable $graceEndsOn,
        public int $graceDays,
        public bool $isOverdue,
        public bool $isWithinGrace,
        public bool $isSuspendable,
        public int $daysUntilDue,
    ) {}
}
