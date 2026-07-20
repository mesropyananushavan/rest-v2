<?php

declare(strict_types=1);

namespace App\Modules\Menu\Application;

use App\Modules\Menu\Infrastructure\Models\MenuCategory;
use App\Support\I18n\LocalizedText;

final class UpdateMenuCategory
{
    use RecordsMenuAction;

    public function __invoke(int $categoryId, LocalizedText $name, int $sortOrder, bool $active): MenuCategory
    {
        $startedAt = microtime(true);

        $category = MenuCategory::query()->findOrFail($categoryId);
        $category->update([
            'translated_name' => $name->toArray(),
            'sort_order' => $sortOrder,
            'active' => $active,
        ]);

        $this->logSuccess('menu.categories.update', $startedAt, [
            'category_id' => (int) $category->id,
        ]);

        return $category;
    }
}
