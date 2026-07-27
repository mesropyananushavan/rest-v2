<?php

declare(strict_types=1);

namespace App\Modules\Orders\Application;

use DateTimeInterface;

final readonly class TableOccupancy
{
    public function __construct(
        public int $tableId,
        public int $orderId,
        public DateTimeInterface $openedAt,
        public int $clientCount,
        public int $totalMinor,
        public string $currency,
        public ?int $waiterId,
    ) {}
}
