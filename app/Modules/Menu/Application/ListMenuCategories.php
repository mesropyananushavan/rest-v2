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
    public function __invoke(): EloquentCollection
    {
        $startedAt = microtime(true);

        $categories = MenuCategory::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $this->logSuccess('menu.categories.list', $startedAt, [
            'category_count' => $categories->count(),
        ]);

        return $categories;
    }
}
