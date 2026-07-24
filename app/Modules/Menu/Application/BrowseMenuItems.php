<?php

declare(strict_types=1);

namespace App\Modules\Menu\Application;

use App\Modules\Menu\Infrastructure\Models\MenuCategory;
use App\Modules\Menu\Infrastructure\Models\MenuItem;
use Illuminate\Pagination\LengthAwarePaginator;

final class BrowseMenuItems
{
    public function __construct(
        private readonly PaginateMenuItems $paginateItems,
        private readonly SearchMenuItems $searchItems,
        private readonly ResolveMenuCategorySelection $categorySelection,
        private readonly ResolveMenuItemListCategory $listCategory,
    ) {}

    /**
     * @param  'active'|'archived'|'all'  $archiveMode
     * @return LengthAwarePaginator<int, MenuItem>
     */
    public function __invoke(
        ?int $categoryId = null,
        ?string $search = null,
        bool $includeInactive = false,
        string $archiveMode = 'active',
        int $perPage = 25,
        int $page = 1,
    ): LengthAwarePaginator {
        $normalizedSearch = $this->normalizedSearch($search);

        if ($normalizedSearch !== null) {
            return ($this->searchItems)(
                $normalizedSearch,
                $includeInactive,
                $archiveMode,
                $perPage,
                $page,
            );
        }

        $category = $categoryId === null
            ? ($this->categorySelection)(null, $archiveMode)
            : ($this->listCategory)($categoryId);

        if (! $category instanceof MenuCategory) {
            /** @var LengthAwarePaginator<int, MenuItem> $emptyItems */
            $emptyItems = new LengthAwarePaginator([], 0, $perPage, max(1, $page), [
                'pageName' => 'page',
                'path' => LengthAwarePaginator::resolveCurrentPath(),
            ]);

            return $emptyItems;
        }

        return ($this->paginateItems)(
            (int) $category->id,
            $includeInactive,
            $archiveMode,
            $perPage,
            $page,
        );
    }

    private function normalizedSearch(?string $search): ?string
    {
        if ($search === null) {
            return null;
        }

        $search = trim($search);

        return $search === '' ? null : $search;
    }
}
