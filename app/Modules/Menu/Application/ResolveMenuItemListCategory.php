<?php

declare(strict_types=1);

namespace App\Modules\Menu\Application;

use App\Modules\Menu\Domain\MenuDomainException;
use App\Modules\Menu\Infrastructure\Models\MenuCategory;
use App\Modules\Menu\Infrastructure\Models\MenuItem;
use App\Modules\Tenancy\Contracts\BranchContext;

final class ResolveMenuItemListCategory
{
    use RecordsMenuAction;

    public function __construct(
        private readonly BranchContext $branches,
    ) {}

    public function __invoke(int $categoryId): MenuCategory
    {
        $startedAt = microtime(true);
        $branchId = $this->branches->id();

        if ($branchId === null) {
            $exception = MenuDomainException::branchContextRequired();
            $this->logDomainFailure('menu.items.resolve_list_category', $exception, $startedAt, [
                'category_id' => $categoryId,
            ]);

            throw $exception;
        }

        $category = MenuCategory::query()
            ->whereKey($categoryId)
            ->whereNotNull('parent_id')
            ->where('active', true)
            ->firstOrFail();

        if ($this->hasItems($category) && ! $this->hasVisibleItemsForBranch($category, $branchId)) {
            abort(404);
        }

        $this->logSuccess('menu.items.resolve_list_category', $startedAt, [
            'branch_id' => $branchId,
            'category_id' => (int) $category->id,
        ]);

        return $category;
    }

    private function hasItems(MenuCategory $category): bool
    {
        return MenuItem::query()
            ->withTrashed()
            ->where('category_id', (int) $category->id)
            ->exists();
    }

    private function hasVisibleItemsForBranch(MenuCategory $category, int $branchId): bool
    {
        return MenuItem::query()
            ->where('category_id', (int) $category->id)
            ->where('branch_id', $branchId)
            ->where('active', true)
            ->exists();
    }
}
