<?php

declare(strict_types=1);

namespace App\Modules\Menu\Application;

use App\Modules\Menu\Infrastructure\Models\MenuCategory;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

final class ListMenuCategories
{
    use RecordsMenuAction;

    /**
     * @return EloquentCollection<int, MenuCategory>
     */
    public function __invoke(bool $includeArchived = false): EloquentCollection
    {
        $startedAt = microtime(true);

        $categories = MenuCategory::query()
            ->with('parent')
            ->whereNotNull('parent_id')
            ->when($includeArchived, fn ($query) => $query->withTrashed())
            ->orderByRaw('(select root_categories.sort_order from menu_categories as root_categories where root_categories.id = menu_categories.parent_id)')
            ->orderByRaw('(select root_categories.id from menu_categories as root_categories where root_categories.id = menu_categories.parent_id)')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $this->logSuccess('menu.categories.list', $startedAt, [
            'category_count' => $categories->count(),
            'include_archived' => $includeArchived,
        ]);

        return $categories;
    }
}
