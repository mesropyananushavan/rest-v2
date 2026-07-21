<?php

declare(strict_types=1);

namespace App\Modules\Menu\Application;

use App\Modules\Menu\Application\Concerns\FiltersLocalizedNames;
use App\Modules\Menu\Infrastructure\Models\MenuCategory;
use Illuminate\Pagination\LengthAwarePaginator;

final class PaginateMenuCategories
{
    use FiltersLocalizedNames;
    use RecordsMenuAction;

    private const DEFAULT_PER_PAGE = 25;

    private const MAX_PER_PAGE = 50;

    /**
     * @return LengthAwarePaginator<int, MenuCategory>
     */
    public function __invoke(
        ?string $search = null,
        bool $includeArchived = false,
        int $perPage = self::DEFAULT_PER_PAGE,
        int $page = 1,
    ): LengthAwarePaginator {
        $startedAt = microtime(true);
        $perPage = $this->boundedPerPage($perPage);
        $page = max(1, $page);

        $query = MenuCategory::query()
            ->when($includeArchived, fn ($query) => $query->withTrashed());

        $this->filterLocalizedName($query, 'translated_name', $search);

        /** @var LengthAwarePaginator<int, MenuCategory> $categories */
        $categories = $query
            ->orderBy('sort_order')
            ->orderByRaw($this->localizedNameOrderExpression($query, 'translated_name', app()->getLocale()))
            ->orderBy('id')
            ->paginate($perPage, ['*'], 'page', $page);

        $this->logSuccess('menu.categories.paginate', $startedAt, [
            'category_count' => $categories->count(),
            'include_archived' => $includeArchived,
            'page' => $page,
            'per_page' => $perPage,
            'search_present' => $this->normalizedSearch($search) !== null,
            'total' => $categories->total(),
        ]);

        return $categories;
    }

    private function boundedPerPage(int $perPage): int
    {
        return min(self::MAX_PER_PAGE, max(1, $perPage));
    }
}
