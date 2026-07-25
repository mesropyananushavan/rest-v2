<?php

declare(strict_types=1);

namespace App\Modules\Menu\Contracts;

use App\Support\I18n\LocalizedText;
use App\Support\Money\Money;

final readonly class MenuItemSummary
{
    public function __construct(
        public int $id,
        public int $branchId,
        public LocalizedText $name,
        public Money $price,
    ) {}
}
