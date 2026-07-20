<?php

declare(strict_types=1);

namespace App\Support\Money;

use InvalidArgumentException;

final readonly class Money
{
    public function __construct(
        public int $minor,
        public string $currency,
    ) {
        if ($minor < 0) {
            throw new InvalidArgumentException('Money minor amount must be zero or greater.');
        }

        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new InvalidArgumentException('Money currency must be an ISO 4217 code.');
        }
    }
}
