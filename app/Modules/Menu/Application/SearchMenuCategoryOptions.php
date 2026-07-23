<?php

declare(strict_types=1);

namespace App\Modules\Menu\Application;

use App\Modules\Menu\Application\Concerns\FiltersLocalizedNames;
use App\Modules\Menu\Infrastructure\Models\MenuCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use InvalidArgumentException;

final class SearchMenuCategoryOptions
{
    use FiltersLocalizedNames;
    use RecordsMenuAction;

    public const MODE_ROOTS = 'roots';

    public const MODE_SUBCATEGORIES = 'subcategories';

    private const DEFAULT_PER_PAGE = 10;

    private const MAX_PER_PAGE = 25;

    /**
     * @return array{options: list<array{id: int, label: string}>, has_more: bool, next_page: int|null}
     */
    public function __invoke(
        string $mode,
        ?string $search = null,
        int $perPage = self::DEFAULT_PER_PAGE,
        int $page = 1,
        ?int $excludeId = null,
    ): array {
        $startedAt = microtime(true);
        $mode = $this->validatedMode($mode);
        $perPage = $this->boundedPerPage($perPage);
        $page = max(1, $page);
        $normalizedSearch = $this->normalizedSearch($search);

        $query = $this->queryForMode($mode, $excludeId);
        $this->filterLocalizedName($query, 'translated_name', $normalizedSearch);
        $this->sort($query, $mode);

        /** @var LengthAwarePaginator<int, MenuCategory> $categories */
        $categories = $query->paginate($perPage, ['*'], 'page', $page);

        $this->logSuccess('menu.categories.options.search', $startedAt, [
            'category_count' => $categories->count(),
            'exclude_id' => $excludeId,
            'has_more' => $categories->hasMorePages(),
            'mode' => $mode,
            'page' => $page,
            'per_page' => $perPage,
            'search_present' => $normalizedSearch !== null,
        ]);

        /** @var list<array{id: int, label: string}> $options */
        $options = array_values($categories
            ->getCollection()
            ->map(fn (MenuCategory $category): array => $this->option($category))
            ->all());

        return [
            'options' => $options,
            'has_more' => $categories->hasMorePages(),
            'next_page' => $categories->hasMorePages() ? $categories->currentPage() + 1 : null,
        ];
    }

    /**
     * @return array{id: int, label: string}|null
     */
    public function selectedOption(string $mode, ?int $categoryId, ?int $excludeId = null): ?array
    {
        if ($categoryId === null || $categoryId <= 0) {
            return null;
        }

        $mode = $this->validatedMode($mode);
        $query = $this->queryForMode($mode, $excludeId);

        $category = $query->whereKey($categoryId)->first();

        if (! $category instanceof MenuCategory) {
            return null;
        }

        return $this->option($category);
    }

    /**
     * @return Builder<MenuCategory>
     */
    private function queryForMode(string $mode, ?int $excludeId): Builder
    {
        $query = MenuCategory::query();

        if ($mode === self::MODE_ROOTS) {
            return $query
                ->whereNull('parent_id')
                ->when(
                    $excludeId !== null,
                    fn (Builder $query): Builder => $query->whereKeyNot($excludeId),
                );
        }

        return $query
            ->with('parent')
            ->whereNotNull('parent_id');
    }

    /**
     * @param  Builder<MenuCategory>  $query
     */
    private function sort(Builder $query, string $mode): void
    {
        if ($mode === self::MODE_SUBCATEGORIES) {
            $query
                ->orderByRaw('(select root_categories.sort_order from menu_categories as root_categories where root_categories.id = menu_categories.parent_id)')
                ->orderByRaw('(select root_categories.id from menu_categories as root_categories where root_categories.id = menu_categories.parent_id)');
        }

        $query
            ->orderBy('sort_order')
            ->orderByRaw($this->localizedNameOrderExpression($query, 'translated_name', app()->getLocale()))
            ->orderBy('id');
    }

    /**
     * @return array{id: int, label: string}
     */
    private function option(MenuCategory $category): array
    {
        $locale = app()->getLocale();
        $name = $category->translatedName()->forLocale($locale);
        $parent = $category->parent;

        return [
            'id' => (int) $category->id,
            'label' => $parent instanceof MenuCategory
                ? $parent->translatedName()->forLocale($locale).' / '.$name
                : $name,
        ];
    }

    private function boundedPerPage(int $perPage): int
    {
        return min(self::MAX_PER_PAGE, max(1, $perPage));
    }

    /**
     * @return self::MODE_ROOTS|self::MODE_SUBCATEGORIES
     */
    private function validatedMode(string $mode): string
    {
        if (! in_array($mode, [self::MODE_ROOTS, self::MODE_SUBCATEGORIES], true)) {
            throw new InvalidArgumentException('Unsupported menu category option mode.');
        }

        return $mode;
    }
}
