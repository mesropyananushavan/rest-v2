<?php

declare(strict_types=1);

namespace App\Modules\Orders\Application;

final readonly class OrderWorkspaceItem
{
    /**
     * @param  array{hy: string, ru: string, en: string}|null  $nameSnapshot
     */
    public function __construct(
        public int $id,
        public ?int $subtableId,
        public ?array $nameSnapshot,
        public int $qty,
        public int $unitPriceMinor,
        public int $discountMinor,
        public int $totalMinor,
        public string $currency,
    ) {}
}
