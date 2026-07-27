<?php

declare(strict_types=1);

namespace App\Modules\Menu\Contracts;

final readonly class SellableMenuBrowseResult
{
    /**
     * @param  list<SellableMenuCategoryGroup>  $categoryGroups
     * @param  list<SellableMenuItem>  $items
     */
    public function __construct(
        public array $categoryGroups,
        public array $items,
        public ?int $selectedCategoryId,
        public int $categoryPage,
        public bool $hasMoreCategoryPages,
        public int $itemPage,
        public bool $hasMoreItemPages,
    ) {}
}
