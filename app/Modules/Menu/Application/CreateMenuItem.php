<?php

declare(strict_types=1);

namespace App\Modules\Menu\Application;

use App\Modules\Menu\Domain\MenuDomainException;
use App\Modules\Menu\Infrastructure\Models\MenuCategory;
use App\Modules\Menu\Infrastructure\Models\MenuItem;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Support\I18n\LocalizedText;
use App\Support\Money\Money;

final class CreateMenuItem
{
    use RecordsMenuAction;

    public function __construct(
        private readonly BranchContext $branches,
    ) {}

    public function __invoke(
        int $categoryId,
        LocalizedText $name,
        ?LocalizedText $description,
        Money $price,
        int $sortOrder = 0,
        bool $active = true,
    ): MenuItem {
        $startedAt = microtime(true);
        $branchId = $this->branches->id();

        if ($branchId === null) {
            $exception = MenuDomainException::branchContextRequired();
            $this->logDomainFailure('menu.items.create', $exception, $startedAt, [
                'category_id' => $categoryId,
            ]);

            throw $exception;
        }

        $category = MenuCategory::query()->findOrFail($categoryId);

        $item = MenuItem::query()->create([
            'branch_id' => $branchId,
            'category_id' => (int) $category->id,
            'translated_name' => $name->toArray(),
            'translated_description' => $description?->toArray(),
            'price_minor' => $price->minor,
            'currency' => $price->currency,
            'sort_order' => $sortOrder,
            'active' => $active,
        ]);

        $this->logSuccess('menu.items.create', $startedAt, [
            'branch_id' => $branchId,
            'category_id' => (int) $category->id,
            'item_id' => (int) $item->id,
            'price_minor' => $price->minor,
            'currency' => $price->currency,
        ]);

        return $item;
    }
}
