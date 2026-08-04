<?php

declare(strict_types=1);

namespace App\Modules\Orders\Contracts;

final readonly class PayableOrderSnapshot
{
    public function __construct(
        public int $orderId,
        public int $tenantId,
        public int $branchId,
        public string $status,
        public string $currency,
        public int $totalMinor,
    ) {}

    /**
     * Temporary derived balance until payment allocations exist.
     *
     * Future payment allocation records must become the authoritative source
     * for paid and remaining amounts; this method must not be treated as
     * immutable financial history.
     */
    public function currentRemainingPayableMinor(): int
    {
        return $this->totalMinor;
    }
}
