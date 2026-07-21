<?php

declare(strict_types=1);

namespace App\Modules\Menu\Application;

use App\Modules\Menu\Infrastructure\Models\MenuCategory;
use App\Modules\Menu\Infrastructure\Models\MenuItem;
use App\Modules\Menu\Infrastructure\Storage\MenuItemImageStorage;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

final class ForceDeleteMenuCategory
{
    use RecordsMenuAction;

    public function __construct(
        private readonly MenuItemImageStorage $storage,
    ) {}

    public function __invoke(int $categoryId): void
    {
        $startedAt = microtime(true);
        $deletedItemCount = 0;

        $category = MenuCategory::onlyTrashed()->findOrFail($categoryId);

        MenuItem::onlyTrashed()
            ->where('category_id', $categoryId)
            ->select(['id', 'internal_image', 'public_image'])
            ->chunkById(100, function (EloquentCollection $items) use (&$deletedItemCount): void {
                /** @var EloquentCollection<int, MenuItem> $items */
                foreach ($items as $item) {
                    $internalImage = $this->imageMetadata($item, 'internal_image');
                    $publicImage = $this->imageMetadata($item, 'public_image');

                    $item->forceDelete();
                    $deletedItemCount++;

                    $this->storage->delete($internalImage);
                    $this->storage->delete($publicImage);
                }
            });

        $category->forceDelete();

        $this->logSuccess('menu.categories.force_delete', $startedAt, [
            'category_id' => $categoryId,
            'deleted_item_count' => $deletedItemCount,
        ]);
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
