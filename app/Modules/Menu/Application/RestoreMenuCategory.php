<?php

declare(strict_types=1);

namespace App\Modules\Menu\Application;

use App\Modules\Menu\Domain\MenuDomainException;
use App\Modules\Menu\Infrastructure\Models\MenuCategory;
use App\Modules\Menu\Infrastructure\Models\MenuItem;
use Illuminate\Support\Facades\DB;
use UnexpectedValueException;

final class RestoreMenuCategory
{
    use RecordsMenuAction;

    public function __invoke(int $categoryId): void
    {
        $startedAt = microtime(true);
        $restoredSubcategoryCount = 0;
        $restoredItemCount = 0;
        $categoryLevel = 'root';

        DB::transaction(function () use ($categoryId, $startedAt, &$restoredSubcategoryCount, &$restoredItemCount, &$categoryLevel): void {
            $category = MenuCategory::withTrashed()->findOrFail($categoryId);
            $restoredAt = now();

            if ($category->parent_id !== null) {
                $categoryLevel = 'subcategory';
                $parent = MenuCategory::withTrashed()->findOrFail((int) $category->parent_id);

                if ($parent->trashed()) {
                    $exception = MenuDomainException::restoreParentCategoryFirst();
                    $this->logDomainFailure('menu.categories.restore', $exception, $startedAt, [
                        'category_id' => $categoryId,
                        'parent_category_id' => (int) $parent->id,
                    ]);

                    throw $exception;
                }

                $category->forceFill([
                    'deleted_at' => null,
                    'archived_with_category_id' => null,
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

                return;
            }

            $subcategories = MenuCategory::withTrashed()
                ->where('parent_id', $categoryId)
                ->get(['id']);
            $subcategoryIds = [];

            foreach ($subcategories as $subcategory) {
                $subcategoryIds[] = $this->categoryId($subcategory);
            }

            $category->forceFill([
                'deleted_at' => null,
                'archived_with_category_id' => null,
                'updated_at' => $restoredAt,
            ])->save();

            $restoredSubcategoryCount = MenuCategory::withTrashed()
                ->whereIn('id', $subcategoryIds)
                ->where('archived_with_category_id', $categoryId)
                ->update([
                    'deleted_at' => null,
                    'archived_with_category_id' => null,
                    'updated_at' => $restoredAt,
                ]);

            $restoredItemCount = MenuItem::withTrashed()
                ->whereIn('category_id', $subcategoryIds)
                ->where('archived_with_category_id', $categoryId)
                ->update([
                    'deleted_at' => null,
                    'archived_with_category_id' => null,
                    'updated_at' => $restoredAt,
                ]);
        });

        $this->logSuccess('menu.categories.restore', $startedAt, [
            'category_id' => $categoryId,
            'category_level' => $categoryLevel,
            'restored_subcategory_count' => $restoredSubcategoryCount,
            'restored_item_count' => $restoredItemCount,
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
