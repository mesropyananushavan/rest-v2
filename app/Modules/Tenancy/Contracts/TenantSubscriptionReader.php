<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Contracts;

use DateTimeInterface;

interface TenantSubscriptionReader
{
    public function statusForTenant(int $tenantId, DateTimeInterface $now): ?TenantSubscriptionStatus;

    /**
     * @return list<int>
     */
    public function suspendableTenantIds(DateTimeInterface $now): array;
}
