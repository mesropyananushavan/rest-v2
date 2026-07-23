<?php

declare(strict_types=1);

namespace App\Modules\Menu\Application;

use App\Modules\Menu\Infrastructure\Models\MenuCategory;
use App\Modules\Menu\Infrastructure\Models\MenuItem;
use App\Modules\Menu\Infrastructure\Storage\MenuItemImageStorage;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use UnexpectedValueException;

final class ForceDeleteMenuCategory
{
    use RecordsMenuAction;

    public function __construct(
        private readonly MenuItemImageStorage $storage,
    ) {}

    public function __invoke(int $categoryId): void
    {
        $startedAt = microtime(true);
        $deletedSubcategoryCount = 0;
        $deletedItemCount = 0;
        $categoryLevel = 'root';
        $imagesToDelete = [];
        $before = null;

        DB::transaction(function () use ($categoryId, &$before, &$deletedSubcategoryCount, &$deletedItemCount, &$categoryLevel, &$imagesToDelete): void {
            $category = MenuCategory::onlyTrashed()->findOrFail($categoryId);
            $before = $this->menuCategoryAuditPayload($category);

            if ($category->parent_id !== null) {
                $categoryLevel = 'subcategory';
                $deletedItemCount = $this->forceDeleteItemsForCategories([$categoryId], $imagesToDelete);

                $category->forceDelete();

                $this->auditMenuMutation('menu.category.permanently_deleted', 'menu_category', $categoryId, $before, [
                    'deleted' => true,
                    'cascade' => [
                        'category_level' => $categoryLevel,
                        'deleted_item_count' => $deletedItemCount,
                        'deleted_subcategory_count' => 0,
                    ],
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

            $deletedItemCount = $this->forceDeleteItemsForCategories($subcategoryIds, $imagesToDelete);

            MenuCategory::withTrashed()
                ->whereIn('id', $subcategoryIds)
                ->orderBy('id')
                ->get()
                ->each(function (MenuCategory $subcategory) use (&$deletedSubcategoryCount): void {
                    $subcategory->forceDelete();
                    $deletedSubcategoryCount++;
                });

            $category->forceDelete();

            $this->auditMenuMutation('menu.category.permanently_deleted', 'menu_category', $categoryId, $before, [
                'deleted' => true,
                'cascade' => [
                    'category_level' => $categoryLevel,
                    'deleted_item_count' => $deletedItemCount,
                    'deleted_subcategory_count' => $deletedSubcategoryCount,
                ],
            ]);
        });

        foreach ($imagesToDelete as $metadata) {
            $this->storage->delete($metadata);
        }

        $this->logSuccess('menu.categories.force_delete', $startedAt, [
            'category_id' => $categoryId,
            'category_level' => $categoryLevel,
            'deleted_subcategory_count' => $deletedSubcategoryCount,
            'deleted_item_count' => $deletedItemCount,
        ]);
    }

    /**
     * @param  list<int>  $categoryIds
     * @param  list<array<string, mixed>>  $imagesToDelete
     */
    private function forceDeleteItemsForCategories(array $categoryIds, array &$imagesToDelete): int
    {
        $deletedItemCount = 0;

        if ($categoryIds === []) {
            return 0;
        }

        MenuItem::withTrashed()
            ->whereIn('category_id', $categoryIds)
            ->select(['id', 'internal_image', 'public_image'])
            ->chunkById(100, function (EloquentCollection $items) use (&$deletedItemCount, &$imagesToDelete): void {
                /** @var EloquentCollection<int, MenuItem> $items */
                foreach ($items as $item) {
                    $internalImage = $this->imageMetadata($item, 'internal_image');
                    $publicImage = $this->imageMetadata($item, 'public_image');

                    if ($internalImage !== null) {
                        $imagesToDelete[] = $internalImage;
                    }

                    if ($publicImage !== null) {
                        $imagesToDelete[] = $publicImage;
                    }

                    $item->forceDelete();
                    $deletedItemCount++;
                }
            });

        return $deletedItemCount;
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

    /**
     * @return array<string, mixed>|null
     */
    private function imageMetadata(MenuItem $item, string $column): ?array
    {
        $metadata = $item->getAttribute($column);

        if (! is_array($metadata)) {
            return null;
        }

        /** @var array<string, mixed> $metadata */
        return $metadata;
    }
}
