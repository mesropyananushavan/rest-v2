<?php

declare(strict_types=1);

namespace App\Modules\Menu\Application;

use App\Modules\Menu\Infrastructure\Models\MenuCategory;
use App\Modules\Menu\Infrastructure\Models\MenuItem;
use Illuminate\Support\Facades\DB;

final class RestoreMenuCategory
{
    use RecordsMenuAction;

    public function __invoke(int $categoryId): void
    {
        $startedAt = microtime(true);
        $restoredItemCount = 0;

        DB::transaction(function () use ($categoryId, &$restoredItemCount): void {
            $category = MenuCategory::withTrashed()->findOrFail($categoryId);
            $restoredAt = now();

            $category->forceFill([
                'deleted_at' => null,
                'updated_at' => $restoredAt,
            ])->save();

            $restoredItemCount = MenuItem::withTrashed()
                ->where('category_id', $categoryId)
                ->where('archived_with_category_id', $categoryId)
                ->update([
                    'deleted_at' => null,
                    'archived_with_category_id' => null,
                    'updated_at' => $restoredAt,
                ]);
        });

        $this->logSuccess('menu.categories.restore', $startedAt, [
            'category_id' => $categoryId,
            'restored_item_count' => $restoredItemCount,
        ]);
    }
}
