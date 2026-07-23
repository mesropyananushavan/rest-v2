<?php

declare(strict_types=1);

namespace App\Modules\Menu\Application;

use App\Modules\Menu\Application\Concerns\BuildsMenuCategoryTreeQueries;
use App\Modules\Menu\Application\Concerns\FiltersLocalizedNames;
use App\Modules\Menu\Infrastructure\Models\MenuCategory;
use Illuminate\Database\Eloquent\Builder;

final class ResolveMenuCategorySelection
{
    use BuildsMenuCategoryTreeQueries;
    use FiltersLocalizedNames;
    use RecordsMenuAction;

    /**
     * @param  'active'|'archived'|'all'  $archiveMode
     */
    public function __invoke(?int $requestedCategoryId = null, string $archiveMode = 'active'): ?MenuCategory
    {
        $startedAt = microtime(true);
        $selectedCategory = $this->resolve($requestedCategoryId, $archiveMode);

        $this->logSuccess('menu.categories.resolve_selection', $startedAt, [
            'archive_mode' => $archiveMode,
            'requested_category_id' => $requestedCategoryId,
            'selected_category_id' => $selectedCategory instanceof MenuCategory ? (int) $selectedCategory->id : null,
        ]);

        return $selectedCategory;
    }

    /**
     * @param  'active'|'archived'|'all'  $archiveMode
     */
    private function resolve(?int $requestedCategoryId, string $archiveMode): ?MenuCategory
    {
        if ($requestedCategoryId !== null) {
            $selectedCategory = $this->orderedSelectableSubcategoryQuery($archiveMode)
                ->whereKey($requestedCategoryId)
                ->first();

            if ($selectedCategory instanceof MenuCategory) {
                return $selectedCategory;
            }

            $requestedRoot = MenuCategory::withTrashed()
                ->whereNull('parent_id')
                ->whereKey($requestedCategoryId)
                ->first();

            if ($requestedRoot instanceof MenuCategory) {
                $selectedCategory = $this->orderedSelectableSubcategoryQuery($archiveMode)
                    ->where('parent_id', (int) $requestedRoot->id)
                    ->first();

                if ($selectedCategory instanceof MenuCategory) {
                    return $selectedCategory;
                }

                return null;
            }
        }

        return $this->orderedSelectableSubcategoryQuery($archiveMode)->first();
    }

    /**
     * @param  'active'|'archived'|'all'  $archiveMode
     * @return Builder<MenuCategory>
     */
    private function orderedSelectableSubcategoryQuery(string $archiveMode): Builder
    {
        $query = $this->selectableSubcategoryQuery($archiveMode);

        return $query
            ->orderByRaw('(select root_categories.sort_order from menu_categories as root_categories where root_categories.id = menu_categories.parent_id)')
            ->orderByRaw('(select root_categories.id from menu_categories as root_categories where root_categories.id = menu_categories.parent_id)')
            ->orderBy('sort_order')
            ->orderByRaw($this->localizedNameOrderExpression($query, 'translated_name', app()->getLocale()))
            ->orderBy('id');
    }
}
