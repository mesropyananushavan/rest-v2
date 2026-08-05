<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application;

final readonly class CaptureCashPaymentCommand
{
    public function __construct(
        public int $orderId,
        public int $cashboxId,
        public int $expectedAmountMinor,
        public string $expectedCurrency,
        public string $idempotencyKey,
    ) {}
}
