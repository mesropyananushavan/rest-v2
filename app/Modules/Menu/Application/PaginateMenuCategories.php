<?php

declare(strict_types=1);

namespace App\Modules\Menu\Application;

use App\Modules\Menu\Application\Concerns\FiltersLocalizedNames;
use App\Modules\Menu\Infrastructure\Models\MenuCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final class PaginateMenuCategories
{
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

        $query = MenuCategory::query();
        $this->applyArchiveMode($query, $archiveMode);

        $this->filterLocalizedName($query, 'translated_name', $search);

        /** @var LengthAwarePaginator<int, MenuCategory> $categories */
        $categories = $query
            ->orderBy('sort_order')
            ->orderByRaw($this->localizedNameOrderExpression($query, 'translated_name', app()->getLocale()))
            ->orderBy('id')
            ->paginate($perPage, ['*'], 'page', $page);

        $this->logSuccess('menu.categories.paginate', $startedAt, [
            'archive_mode' => $archiveMode,
            'category_count' => $categories->count(),
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

    /**
     * @param  Builder<MenuCategory>  $query
     * @param  'active'|'archived'|'all'  $archiveMode
     */
    private function applyArchiveMode(Builder $query, string $archiveMode): void
    {
        match ($archiveMode) {
            'active' => null,
            'archived' => $query
                ->withTrashed()
                ->where(
                    fn (Builder $query): Builder => $query
                        ->whereNotNull('deleted_at')
                        ->orWhereHas('items', fn (Builder $query): Builder => $query->onlyTrashed()),
                ),
            'all' => $query->withTrashed(),
        };
    }
}
