<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application;

final readonly class FullCashPaymentPreview
{
    public function __construct(
        public int $orderId,
        public int $amountMinor,
        public string $currency,
    ) {}
}
