<?php

declare(strict_types=1);

namespace App\Modules\Menu\Application;

use App\Modules\Menu\Infrastructure\Models\MenuCategory;
use App\Modules\Menu\Infrastructure\Models\MenuItem;
use Illuminate\Support\Facades\DB;
use UnexpectedValueException;

final class ArchiveMenuCategory
{
    use RecordsMenuAction;

    public function __invoke(int $categoryId): void
    {
        $startedAt = microtime(true);
        $archivedSubcategoryCount = 0;
        $archivedItemCount = 0;
        $categoryLevel = 'root';

        DB::transaction(function () use ($categoryId, &$archivedSubcategoryCount, &$archivedItemCount, &$categoryLevel): void {
            $category = MenuCategory::query()->findOrFail($categoryId);
            $archivedAt = now();

            if ($category->parent_id !== null) {
                $categoryLevel = 'subcategory';

                $archivedItemCount = MenuItem::query()
                    ->where('category_id', $categoryId)
                    ->whereNull('deleted_at')
                    ->whereNull('archived_with_category_id')
                    ->update([
                        'deleted_at' => $archivedAt,
                        'archived_with_category_id' => $categoryId,
                        'updated_at' => $archivedAt,
                    ]);

                $category->forceFill([
                    'deleted_at' => $archivedAt,
                    'updated_at' => $archivedAt,
                ])->save();

                return;
            }

            $subcategories = MenuCategory::query()
                ->where('parent_id', $categoryId)
                ->get(['id']);
            $subcategoryIds = [];

            foreach ($subcategories as $subcategory) {
                $subcategoryIds[] = $this->categoryId($subcategory);
            }

            $archivedItemCount = MenuItem::query()
                ->whereIn('category_id', $subcategoryIds)
                ->whereNull('deleted_at')
                ->whereNull('archived_with_category_id')
                ->update([
                    'deleted_at' => $archivedAt,
                    'archived_with_category_id' => $categoryId,
                    'updated_at' => $archivedAt,
                ]);

            $archivedSubcategoryCount = MenuCategory::query()
                ->whereIn('id', $subcategoryIds)
                ->whereNull('deleted_at')
                ->whereNull('archived_with_category_id')
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
            'category_level' => $categoryLevel,
            'archived_subcategory_count' => $archivedSubcategoryCount,
            'archived_item_count' => $archivedItemCount,
        ]);
    }

    private function categoryId(MenuCategory $category): int
    {
        $id = $category->getKey();

        if (is_int($id)) {
            return $id;
        }

        if (is_string($id) && $id !== '') {
            return (int) $id;
        }

        throw new UnexpectedValueException('Menu category id is not hydrated.');
    }
}
