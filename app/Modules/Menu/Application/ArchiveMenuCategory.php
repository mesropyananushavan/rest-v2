<?php

declare(strict_types=1);

namespace App\Modules\Menu\Application;

use App\Modules\Menu\Infrastructure\Models\MenuCategory;
use App\Modules\Menu\Infrastructure\Models\MenuItem;
use Illuminate\Support\Facades\DB;

final class ArchiveMenuCategory
{
    use RecordsMenuAction;

    public function __invoke(int $categoryId): void
    {
        $startedAt = microtime(true);
        $archivedItemCount = 0;

        DB::transaction(function () use ($categoryId, &$archivedItemCount): void {
            $category = MenuCategory::query()->findOrFail($categoryId);
            $archivedAt = now();

            $archivedItemCount = MenuItem::query()
                ->where('category_id', $categoryId)
                ->update([
                    'deleted_at' => $archivedAt,
                    'archived_with_category_id' => $categoryId,
                    'updated_at' => $archivedAt,
                ]);

            $category->forceFill([
                'deleted_at' => $archivedAt,
                'updated_at' => $archivedAt,
            ])->save();
        });

        $this->logSuccess('menu.categories.archive', $startedAt, [
            'category_id' => $categoryId,
            'archived_item_count' => $archivedItemCount,
        ]);
    }
}
