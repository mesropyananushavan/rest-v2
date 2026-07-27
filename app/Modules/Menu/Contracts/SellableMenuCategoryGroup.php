<?php

declare(strict_types=1);

namespace App\Modules\Menu\Contracts;

use App\Support\I18n\LocalizedText;

final readonly class SellableMenuCategoryGroup
{
    /**
     * @param  list<SellableMenuCategory>  $categories
     */
    public function __construct(
        public int $id,
        public LocalizedText $name,
        public int $sortOrder,
        public array $categories,
    ) {}
}
