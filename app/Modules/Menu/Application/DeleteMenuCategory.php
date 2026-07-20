<?php

declare(strict_types=1);

namespace App\Modules\Menu\Application;

use App\Modules\Menu\Infrastructure\Models\MenuCategory;

final class DeleteMenuCategory
{
    use RecordsMenuAction;

    public function __invoke(int $categoryId): void
    {
        $startedAt = microtime(true);

        $category = MenuCategory::query()->findOrFail($categoryId);
        $category->delete();

        $this->logSuccess('menu.categories.delete', $startedAt, [
            'category_id' => $categoryId,
        ]);
    }
}
