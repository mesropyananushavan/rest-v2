<?php

declare(strict_types=1);

namespace App\Modules\Menu\Application;

use App\Modules\Menu\Infrastructure\Models\MenuCategory;
use App\Modules\Menu\Infrastructure\Models\MenuItem;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class BrowseMenuItemsResult
{
    /**
     * @param  'active'|'archived'|'all'  $archiveMode
     * @param  LengthAwarePaginator<int, MenuCategory>  $categories
     * @param  LengthAwarePaginator<int, MenuItem>|null  $items
     * @param  LengthAwarePaginator<int, MenuItem>|null  $globalResults
     */
    public function __construct(
        public string $archiveMode,
        public LengthAwarePaginator $categories,
        public ?MenuCategory $selectedCategory,
        public ?LengthAwarePaginator $items,
        public ?LengthAwarePaginator $globalResults,
        public bool $isSearching,
    ) {}

    public function selectedCategoryId(): ?int
    {
        return $this->selectedCategory instanceof MenuCategory
            ? (int) $this->selectedCategory->id
            : null;
    }
}
