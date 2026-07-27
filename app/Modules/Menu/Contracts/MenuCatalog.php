<?php

declare(strict_types=1);

namespace App\Modules\Menu\Contracts;

interface MenuCatalog
{
    public function findSellableInBranch(int $menuItemId, int $branchId): ?MenuItemSummary;

    public function browseSellableInBranch(
        int $branchId,
        ?int $categoryId = null,
        ?string $search = null,
        int $perPage = 25,
        int $page = 1,
        int $categoryPerPage = 25,
        int $categoryPage = 1,
    ): SellableMenuBrowseResult;
}
