<?php

declare(strict_types=1);

namespace App\Modules\Menu\Infrastructure\Catalog;

use App\Modules\Menu\Contracts\MenuCatalog;
use App\Modules\Menu\Contracts\MenuItemSummary;
use App\Modules\Menu\Infrastructure\Models\MenuItem;

final class EloquentMenuCatalog implements MenuCatalog
{
    public function findSellableInBranch(int $menuItemId, int $branchId): ?MenuItemSummary
    {
        $item = MenuItem::query()
            ->where('branch_id', $branchId)
            ->where('active', true)
            ->find($menuItemId);

        if (! $item instanceof MenuItem) {
            return null;
        }

        return new MenuItemSummary(
            id: (int) $item->id,
            branchId: (int) $item->branch_id,
            name: $item->translatedName(),
            price: $item->price(),
        );
    }
}
