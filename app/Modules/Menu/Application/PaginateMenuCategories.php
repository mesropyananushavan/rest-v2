<?php

declare(strict_types=1);

namespace App\Modules\Menu\Application;

use App\Modules\Menu\Application\Concerns\BuildsMenuCategoryTreeQueries;
use App\Modules\Menu\Application\Concerns\FiltersLocalizedNames;
use App\Modules\Menu\Infrastructure\Models\MenuCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

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

        $query = MenuCategory::query()
            ->whereNull('parent_id')
            ->with([
                'subcategories' => function (Relation $relation) use ($archiveMode): void {
                    /** @var Builder<MenuCategory> $query */
                    $query = $relation->getQuery();
                    $this->childCategoryQuery($query, $archiveMode);
                },
            ]);
        $this->applyRootArchiveMode($query, $archiveMode);
        $normalizedSearch = $this->normalizedSearch($search);

        $this->filterRootSearch($query, $normalizedSearch, $archiveMode);

        /** @var LengthAwarePaginator<int, MenuCategory> $categories */
        $categories = $query
            ->orderBy('sort_order')
            ->orderByRaw($this->localizedNameOrderExpression($query, 'translated_name', app()->getLocale()))
            ->orderBy('id')
            ->paginate($perPage, ['*'], 'page', $page);
        $this->attachLoadedParents($categories);

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

    /**
     * @param  LengthAwarePaginator<int, MenuCategory>  $categories
     */
    private function attachLoadedParents(LengthAwarePaginator $categories): void
    {
        foreach ($categories as $rootCategory) {
            foreach ($rootCategory->subcategories as $subcategory) {
                $subcategory->setRelation('parent', $rootCategory);
            }
        }
    }

    /**
     * @param  Builder<MenuCategory>  $query
     * @param  'active'|'archived'|'all'  $archiveMode
     * @return Builder<MenuCategory>
     */
    private function childCategoryQuery(Builder $query, string $archiveMode): Builder
    {
        $this->applyChildArchiveMode($query, $archiveMode);

        return $query
            ->orderBy('sort_order')
            ->orderByRaw($this->localizedNameOrderExpression($query, 'translated_name', app()->getLocale()))
            ->orderBy('id');
    }

    /**
     * @param  Builder<MenuCategory>  $query
     * @param  'active'|'archived'|'all'  $archiveMode
     */
    private function applyRootArchiveMode(Builder $query, string $archiveMode): void
    {
        match ($archiveMode) {
            'active' => null,
            'archived' => $query
                ->withTrashed()
                ->where(
                    fn (Builder $query): Builder => $query
                        ->whereNotNull('deleted_at')
                        ->orWhereIn('id', $this->archivedChildParentIdsQuery())
                        ->orWhereIn('id', $this->archivedItemRootIdsQuery()),
                ),
            'all' => $query->withTrashed(),
        };
    }

    /**
     * @param  Builder<MenuCategory>  $query
     * @param  'active'|'archived'|'all'  $archiveMode
     */
    private function applyChildArchiveMode(Builder $query, string $archiveMode): void
    {
        match ($archiveMode) {
            'active' => null,
            'archived' => $query
                ->withTrashed()
                ->where(
                    fn (Builder $query): Builder => $query
                        ->whereNotNull('deleted_at')
                        ->orWhereIn('id', $this->archivedItemCategoryIdsQuery()),
                ),
            'all' => $query->withTrashed(),
        };
    }

    /**
     * @param  Builder<MenuCategory>  $query
     * @param  'active'|'archived'|'all'  $archiveMode
     */
    private function filterRootSearch(Builder $query, ?string $normalizedSearch, string $archiveMode): void
    {
        if ($normalizedSearch === null) {
            return;
        }

        $matchingChildParentIds = MenuCategory::query()
            ->select('parent_id')
            ->whereNotNull('parent_id');
        $this->applyChildArchiveMode($matchingChildParentIds, $archiveMode);
        $this->filterLocalizedName($matchingChildParentIds, 'translated_name', $normalizedSearch);

        $query->where(function (Builder $query) use ($matchingChildParentIds, $normalizedSearch): Builder {
            $this->filterLocalizedName($query, 'translated_name', $normalizedSearch);

            return $query->orWhereIn('id', $matchingChildParentIds);
        });
    }

    /**
     * @return Builder<MenuCategory>
     */
    private function archivedChildParentIdsQuery(): Builder
    {
        return MenuCategory::query()
            ->withTrashed()
            ->select('parent_id')
            ->whereNotNull('parent_id')
            ->whereNotNull('deleted_at');
    }

    /**
     * @return Builder<MenuCategory>
     */
    private function archivedItemRootIdsQuery(): Builder
    {
        return MenuCategory::query()
            ->withTrashed()
            ->select('parent_id')
            ->whereNotNull('parent_id')
            ->whereIn('id', $this->archivedItemCategoryIdsQuery());
    }

    private function archivedItemCategoryIdsQuery(): QueryBuilder
    {
        return DB::table('menu_items')
            ->select('category_id')
            ->whereNotNull('deleted_at');
    }
}
