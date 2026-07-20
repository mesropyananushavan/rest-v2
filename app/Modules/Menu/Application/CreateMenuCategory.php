<?php

declare(strict_types=1);

namespace App\Modules\Menu\Application;

use App\Modules\Menu\Infrastructure\Models\MenuCategory;
use App\Support\I18n\LocalizedText;

final class CreateMenuCategory
{
    use RecordsMenuAction;

    public function __invoke(LocalizedText $name, int $sortOrder = 0, bool $active = true): MenuCategory
    {
        $startedAt = microtime(true);

        $category = MenuCategory::query()->create([
            'translated_name' => $name->toArray(),
            'sort_order' => $sortOrder,
            'active' => $active,
        ]);

        $this->logSuccess('menu.categories.create', $startedAt, [
            'category_id' => (int) $category->id,
        ]);

        return $category;
    }
}
