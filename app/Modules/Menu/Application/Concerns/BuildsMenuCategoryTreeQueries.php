<?php

declare(strict_types=1);

namespace App\Modules\Menu\Application\Concerns;

use App\Modules\Menu\Infrastructure\Models\MenuCategory;
use Illuminate\Database\Eloquent\Builder;

trait BuildsMenuCategoryTreeQueries
{
    /**
     * @param  'active'|'archived'|'all'  $archiveMode
     * @return Builder<MenuCategory>
     */
    private function selectableSubcategoryQuery(string $archiveMode): Builder
    {
        $query = MenuCategory::query()
            ->with('parent')
            ->whereNotNull('parent_id');

        $this->applyCategoryTreeArchiveMode($query, $archiveMode);

        return $query;
    }

    /**
     * @param  Builder<MenuCategory>  $query
     * @param  'active'|'archived'|'all'  $archiveMode
     */
    private function applyCategoryTreeArchiveMode(Builder $query, string $archiveMode): void
    {
        match ($archiveMode) {
            'active' => $query->whereHas(
                'parent',
                fn (Builder $query): Builder => $query->whereNull('deleted_at'),
            ),
            'archived' => $query
                ->withTrashed()
                ->where(
                    fn (Builder $query): Builder => $query
                        ->whereNotNull('deleted_at')
                        ->orWhereHas('items', function (Builder $query): Builder {
                            /** @phpstan-ignore-next-line SoftDeletes adds onlyTrashed() to this MenuItem relation query at runtime. */
                            $query->onlyTrashed();

                            return $query;
                        }),
                ),
            'all' => $query->withTrashed(),
        };
    }
}
