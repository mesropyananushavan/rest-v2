<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application;

final readonly class CaptureCashPaymentResult
{
    public function __construct(
        public int $paymentId,
        public int $paymentAllocationId,
        public int $cashboxEntryId,
        public int $tenantId,
        public int $branchId,
        public int $orderId,
        public int $cashboxId,
        public int $amountMinor,
        public string $currency,
        public string $idempotencyKey,
        public string $idempotencyFingerprint,
        public bool $replayed,
    ) {}
}
