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
        $before = null;
        $after = null;

        DB::transaction(function () use ($categoryId, $startedAt, &$after, &$before, &$restoredSubcategoryCount, &$restoredItemCount, &$categoryLevel): void {
            $category = MenuCategory::withTrashed()->findOrFail($categoryId);
            $restoredAt = now();
            $before = $this->menuCategoryAuditPayload($category);

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

                $after = $this->menuCategoryAuditPayload($category->refresh()) + [
                    'cascade' => [
                        'category_level' => $categoryLevel,
                        'marker_category_id' => $categoryId,
                        'restored_item_count' => $restoredItemCount,
                        'restored_subcategory_count' => 0,
                    ],
                ];

                $this->auditMenuMutation('menu.category.restored', 'menu_category', $categoryId, $before, $after);

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

            $after = $this->menuCategoryAuditPayload($category->refresh()) + [
                'cascade' => [
                    'category_level' => $categoryLevel,
                    'marker_category_id' => $categoryId,
                    'restored_item_count' => $restoredItemCount,
                    'restored_subcategory_count' => $restoredSubcategoryCount,
                ],
            ];

            $this->auditMenuMutation('menu.category.restored', 'menu_category', $categoryId, $before, $after);
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
