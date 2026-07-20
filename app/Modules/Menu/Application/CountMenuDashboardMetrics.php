<?php

declare(strict_types=1);

namespace App\Modules\Menu\Application;

use App\Modules\Menu\Infrastructure\Models\MenuCategory;
use App\Modules\Menu\Infrastructure\Models\MenuItem;

final class CountMenuDashboardMetrics
{
    /**
     * @return array{categories: int, items: int}
     */
    public function __invoke(): array
    {
        return [
            'categories' => MenuCategory::query()->count(),
            'items' => MenuItem::query()->count(),
        ];
    }
}
