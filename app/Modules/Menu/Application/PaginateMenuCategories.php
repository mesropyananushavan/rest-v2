<?php

declare(strict_types=1);

namespace App\Modules\Menu\Application;

use App\Modules\Menu\Application\Concerns\BuildsMenuCategoryTreeQueries;
use App\Modules\Menu\Application\Concerns\FiltersLocalizedNames;
use App\Modules\Menu\Infrastructure\Models\MenuCategory;
use Illuminate\Pagination\LengthAwarePaginator;

final class PaginateMenuCategories
{
    use BuildsMenuCategoryTreeQueries;
    use FiltersLocalizedNames;
    use RecordsMenuAction;

    private const DEFAULT_PER_PAGE = 25;

    private const MAX_PER_PAGE = 50;

    /**
     * @param  'active'|'archived'|'all'  $archiveMode
     * @return LengthAwarePaginator<int, MenuCategory>
     */
    public function __invoke(
        ?string $search = null,
        string $archiveMode = 'active',
        int $perPage = self::DEFAULT_PER_PAGE,
        int $page = 1,
    ): LengthAwarePaginator {
        $startedAt = microtime(true);
        $perPage = $this->boundedPerPage($perPage);
        $page = max(1, $page);

        $query = $this->selectableSubcategoryQuery($archiveMode);
        $normalizedSearch = $this->normalizedSearch($search);

        $this->filterLocalizedName($query, 'translated_name', $normalizedSearch);

        /** @var LengthAwarePaginator<int, MenuCategory> $categories */
        $categories = $query
            ->orderByRaw('(select root_categories.sort_order from menu_categories as root_categories where root_categories.id = menu_categories.parent_id)')
            ->orderByRaw('(select root_categories.id from menu_categories as root_categories where root_categories.id = menu_categories.parent_id)')
            ->orderBy('sort_order')
            ->orderByRaw($this->localizedNameOrderExpression($query, 'translated_name', app()->getLocale()))
            ->orderBy('id')
            ->paginate($perPage, ['*'], 'page', $page);

        $this->logSuccess('menu.categories.paginate', $startedAt, [
            'archive_mode' => $archiveMode,
            'category_count' => $categories->count(),
            'page' => $page,
            'per_page' => $perPage,
            'search_present' => $normalizedSearch !== null,
            'total' => $categories->total(),
        ]);

        return $categories;
    }

    private function boundedPerPage(int $perPage): int
    {
        return min(self::MAX_PER_PAGE, max(1, $perPage));
    }
}
