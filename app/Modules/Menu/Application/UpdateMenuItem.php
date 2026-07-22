<?php

declare(strict_types=1);

namespace App\Modules\Menu\Application;

use App\Modules\Menu\Domain\MenuDomainException;
use App\Modules\Menu\Infrastructure\Models\MenuCategory;
use App\Modules\Menu\Infrastructure\Models\MenuItem;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Support\I18n\LocalizedText;
use App\Support\Money\Money;

final class UpdateMenuItem
{
    use RecordsMenuAction;

    public function __construct(
        private readonly BranchContext $branches,
    ) {}

    public function __invoke(
        int $itemId,
        int $categoryId,
        LocalizedText $name,
        ?LocalizedText $description,
        Money $price,
        int $sortOrder,
        bool $active,
    ): MenuItem {
        $startedAt = microtime(true);
        $branchId = $this->branches->id();

        if ($branchId === null) {
            $exception = MenuDomainException::branchContextRequired();
            $this->logDomainFailure('menu.items.update', $exception, $startedAt, [
                'category_id' => $categoryId,
                'item_id' => $itemId,
            ]);

            throw $exception;
        }

        $item = MenuItem::query()
            ->where('branch_id', $branchId)
            ->findOrFail($itemId);
        $category = $this->findValidSubcategory($categoryId);

        $item->update([
            'category_id' => (int) $category->id,
            'translated_name' => $name->toArray(),
            'translated_description' => $description?->toArray(),
            'price_minor' => $price->minor,
            'currency' => $price->currency,
            'sort_order' => $sortOrder,
            'active' => $active,
        ]);

        $this->logSuccess('menu.items.update', $startedAt, [
            'branch_id' => $branchId,
            'category_id' => (int) $category->id,
            'item_id' => (int) $item->id,
            'price_minor' => $price->minor,
            'currency' => $price->currency,
        ]);

        return $item;
    }

    private function findValidSubcategory(int $categoryId): MenuCategory
    {
        // Keep TenantScoped enabled here: a category from another tenant resolves to null.
        $category = MenuCategory::query()
            ->whereKey($categoryId)
            ->firstOrFail();

        if ($category->parent_id === null) {
            throw MenuDomainException::itemCategoryMustBeSubcategory();
        }

        return $category;
    }
}
