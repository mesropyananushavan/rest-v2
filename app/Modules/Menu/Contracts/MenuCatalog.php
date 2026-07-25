<?php

declare(strict_types=1);

namespace App\Modules\Menu\Contracts;

interface MenuCatalog
{
    public function findSellableInBranch(int $menuItemId, int $branchId): ?MenuItemSummary;
}
