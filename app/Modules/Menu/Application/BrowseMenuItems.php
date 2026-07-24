<?php

declare(strict_types=1);

namespace App\Modules\Menu\Application;

use App\Modules\Menu\Infrastructure\Models\MenuCategory;
use App\Modules\Menu\Infrastructure\Models\MenuItem;
use Illuminate\Pagination\LengthAwarePaginator;

final class BrowseMenuItems
{
    /**
     * @var list<'active'|'archived'|'all'>
     */
    private const ARCHIVE_MODES = ['active', 'archived', 'all'];

    public function __construct(
        private readonly PaginateMenuCategories $paginateCategories,
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

    /**
     * @param  'active'|'archived'|'all'|string  $archiveMode
     */
    public function forMenuIndex(
        ?int $categoryId = null,
        ?string $search = null,
        ?string $categorySearch = null,
        bool $includeInactive = false,
        string $archiveMode = 'active',
        bool $canViewArchive = false,
        int $categoryPerPage = 25,
        int $itemPerPage = 25,
        int $categoryPage = 1,
        int $itemPage = 1,
        int $searchPage = 1,
    ): BrowseMenuItemsResult {
        $archiveMode = $this->normalizedArchiveMode($archiveMode, $canViewArchive);
        $normalizedSearch = $this->normalizedSearch($search);
        $selectedCategory = ($this->categorySelection)($categoryId, $archiveMode);
        $selectedCategoryId = $selectedCategory instanceof MenuCategory ? (int) $selectedCategory->id : null;
        $categories = ($this->paginateCategories)(
            search: $categorySearch,
            archiveMode: $archiveMode,
            perPage: $categoryPerPage,
            page: $categoryPage,
        );
        $items = $selectedCategoryId === null || $normalizedSearch !== null
            ? null
            : ($this->paginateItems)(
                categoryId: $selectedCategoryId,
                includeInactive: $includeInactive,
                archiveMode: $archiveMode,
                perPage: $itemPerPage,
                page: $itemPage,
            );
        $globalResults = $normalizedSearch === null
            ? null
            : ($this->searchItems)(
                search: $normalizedSearch,
                includeInactive: $includeInactive,
                archiveMode: $archiveMode,
                perPage: $itemPerPage,
                page: $searchPage,
            );

        return new BrowseMenuItemsResult(
            archiveMode: $archiveMode,
            categories: $categories,
            selectedCategory: $selectedCategory,
            items: $items,
            globalResults: $globalResults,
            isSearching: $normalizedSearch !== null,
        );
    }

    /**
     * @param  'active'|'archived'|'all'|string  $archiveMode
     */
    public function selectedCategoryForMenuIndex(int $categoryId, string $archiveMode = 'active', bool $canViewArchive = false): ?MenuCategory
    {
        return ($this->categorySelection)(
            $categoryId,
            $this->normalizedArchiveMode($archiveMode, $canViewArchive),
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

    /**
     * @param  'active'|'archived'|'all'|string  $archiveMode
     * @return 'active'|'archived'|'all'
     */
    private function normalizedArchiveMode(string $archiveMode, bool $canViewArchive): string
    {
        if (! in_array($archiveMode, self::ARCHIVE_MODES, true)) {
            return 'active';
        }

        if (! $canViewArchive && $archiveMode !== 'active') {
            return 'active';
        }

        /** @var 'active'|'archived'|'all' $archiveMode */
        return $archiveMode;
    }
}
