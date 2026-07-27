<?php

declare(strict_types=1);

namespace App\Modules\Menu\Infrastructure\Catalog;

use App\Modules\Menu\Application\Concerns\FiltersLocalizedNames;
use App\Modules\Menu\Contracts\MenuCatalog;
use App\Modules\Menu\Contracts\MenuItemSummary;
use App\Modules\Menu\Contracts\SellableMenuBrowseResult;
use App\Modules\Menu\Contracts\SellableMenuCategory;
use App\Modules\Menu\Contracts\SellableMenuCategoryGroup;
use App\Modules\Menu\Contracts\SellableMenuItem;
use App\Modules\Menu\Infrastructure\Models\MenuCategory;
use App\Modules\Menu\Infrastructure\Models\MenuItem;
use Illuminate\Database\Eloquent\Builder;

final class EloquentMenuCatalog implements MenuCatalog
{
    use FiltersLocalizedNames;

    private const DEFAULT_PER_PAGE = 25;

    private const MAX_PER_PAGE = 50;

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

    public function browseSellableInBranch(
        int $branchId,
        ?int $categoryId = null,
        ?string $search = null,
        int $perPage = self::DEFAULT_PER_PAGE,
        int $page = 1,
        int $categoryPerPage = self::DEFAULT_PER_PAGE,
        int $categoryPage = 1,
    ): SellableMenuBrowseResult {
        $perPage = $this->boundedPerPage($perPage);
        $page = max(1, $page);
        $categoryPerPage = $this->boundedPerPage($categoryPerPage);
        $categoryPage = max(1, $categoryPage);
        $selectedCategoryId = $this->selectedCategoryId($categoryId, $branchId);
        $locale = $this->supportedLocale(app()->getLocale());

        $categories = MenuCategory::query()
            ->whereNotNull('parent_id')
            ->where('active', true)
            ->whereHas('parent', fn (Builder $query): Builder => $query
                ->where('active', true)
                ->whereNull('deleted_at'))
            ->whereHas('items', function (Builder $query) use ($branchId): Builder {
                /** @var Builder<MenuItem> $query */
                return $this->sellableItemQuery($query, $branchId);
            })
            ->with('parent')
            ->orderBy('parent_id')
            ->orderBy('sort_order')
            ->orderByRaw($this->localizedNameOrderExpression(MenuCategory::query(), 'translated_name', $locale))
            ->orderBy('id')
            ->simplePaginate($categoryPerPage, ['*'], 'category_page', $categoryPage);

        $itemsQuery = MenuItem::query()
            ->where('branch_id', $branchId)
            ->where('active', true)
            ->when($selectedCategoryId !== null, fn (Builder $query): Builder => $query->where('category_id', $selectedCategoryId));
        $this->filterLocalizedName($itemsQuery, 'translated_name', $search);

        $items = $itemsQuery
            ->orderBy('sort_order')
            ->orderByRaw($this->localizedNameOrderExpression($itemsQuery, 'translated_name', $locale))
            ->orderBy('id')
            ->simplePaginate($perPage, ['*'], 'page', $page);

        return new SellableMenuBrowseResult(
            categoryGroups: $this->categoryGroups(array_values($categories->getCollection()->all())),
            items: array_values($items->getCollection()
                ->map(fn (MenuItem $item): SellableMenuItem => new SellableMenuItem(
                    id: (int) $item->id,
                    categoryId: (int) $item->category_id,
                    name: $item->translatedName(),
                    price: $item->price(),
                    sortOrder: (int) $item->sort_order,
                ))
                ->all()),
            selectedCategoryId: $selectedCategoryId,
            categoryPage: $categoryPage,
            hasMoreCategoryPages: $categories->hasMorePages(),
            itemPage: $page,
            hasMoreItemPages: $items->hasMorePages(),
        );
    }

    private function boundedPerPage(int $perPage): int
    {
        return min(self::MAX_PER_PAGE, max(1, $perPage));
    }

    private function supportedLocale(string $locale): string
    {
        return in_array($locale, ['hy', 'ru', 'en'], true) ? $locale : 'en';
    }

    private function selectedCategoryId(?int $categoryId, int $branchId): ?int
    {
        if ($categoryId === null || $categoryId < 1) {
            return null;
        }

        $category = MenuCategory::query()
            ->whereKey($categoryId)
            ->whereNotNull('parent_id')
            ->where('active', true)
            ->whereHas('parent', fn (Builder $query): Builder => $query
                ->where('active', true)
                ->whereNull('deleted_at'))
            ->whereHas('items', function (Builder $query) use ($branchId): Builder {
                /** @var Builder<MenuItem> $query */
                return $this->sellableItemQuery($query, $branchId);
            })
            ->first(['id']);

        return $category instanceof MenuCategory ? (int) $category->id : null;
    }

    /**
     * @param  Builder<MenuItem>  $query
     * @return Builder<MenuItem>
     */
    private function sellableItemQuery(Builder $query, int $branchId): Builder
    {
        return $query
            ->where('branch_id', $branchId)
            ->where('active', true);
    }

    /**
     * @param  list<MenuCategory>  $categories
     * @return list<SellableMenuCategoryGroup>
     */
    private function categoryGroups(array $categories): array
    {
        $groups = [];

        foreach ($categories as $category) {
            $parent = $category->parent;

            if (! $parent instanceof MenuCategory) {
                continue;
            }

            $parentId = (int) $parent->id;
            $groups[$parentId] ??= [
                'parent' => $parent,
                'categories' => [],
            ];

            $groups[$parentId]['categories'][] = new SellableMenuCategory(
                id: (int) $category->id,
                parentId: (int) $category->parent_id,
                name: $category->translatedName(),
                sortOrder: (int) $category->sort_order,
            );
        }

        return array_values(array_map(
            static fn (array $group): SellableMenuCategoryGroup => new SellableMenuCategoryGroup(
                id: (int) $group['parent']->id,
                name: $group['parent']->translatedName(),
                sortOrder: (int) $group['parent']->sort_order,
                categories: $group['categories'],
            ),
            $groups,
        ));
    }
}
