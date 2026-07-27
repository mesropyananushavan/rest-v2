<?php

declare(strict_types=1);

namespace App\Modules\Menu\Contracts;

use App\Support\I18n\LocalizedText;

final readonly class SellableMenuCategory
{
    public function __construct(
        public int $id,
        public int $parentId,
        public LocalizedText $name,
        public int $sortOrder,
    ) {}
}
