<?php

declare(strict_types=1);

namespace App\Modules\Menu\Contracts;

use App\Support\I18n\LocalizedText;
use App\Support\Money\Money;

final readonly class SellableMenuItem
{
    public function __construct(
        public int $id,
        public int $categoryId,
        public LocalizedText $name,
        public Money $price,
        public int $sortOrder,
    ) {}
}
