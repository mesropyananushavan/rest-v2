<?php

declare(strict_types=1);

namespace App\Modules\Menu\Application;

use App\Modules\Menu\Infrastructure\Models\MenuCategory;
use App\Modules\Menu\Infrastructure\Models\MenuItem;
use Illuminate\Support\Facades\DB;

final class ForceDeleteMenuCategory
{
    use RecordsMenuAction;

    public function __invoke(int $categoryId): void
    {
        $startedAt = microtime(true);
        $deletedItemCount = 0;

        DB::transaction(function () use ($categoryId, &$deletedItemCount): void {
            $category = MenuCategory::onlyTrashed()->findOrFail($categoryId);

            $deletedItemCount = MenuItem::onlyTrashed()
                ->where('category_id', $categoryId)
                ->forceDelete();

            $category->forceDelete();
        });

        $this->logSuccess('menu.categories.force_delete', $startedAt, [
            'category_id' => $categoryId,
            'deleted_item_count' => $deletedItemCount,
        ]);
    }
}
