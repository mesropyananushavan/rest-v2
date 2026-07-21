<?php

declare(strict_types=1);

namespace App\Modules\Menu\Application;

use App\Modules\Menu\Application\Concerns\FiltersLocalizedNames;
use App\Modules\Menu\Domain\MenuDomainException;
use App\Modules\Menu\Infrastructure\Models\MenuItem;
use App\Modules\Tenancy\Contracts\BranchContext;
use Illuminate\Pagination\LengthAwarePaginator;

final class PaginateMenuItems
{
    use FiltersLocalizedNames;
    use RecordsMenuAction;

    private const DEFAULT_PER_PAGE = 25;

    private const MAX_PER_PAGE = 50;

    public function __construct(
        private readonly BranchContext $branches,
    ) {}

    /**
     * @return LengthAwarePaginator<int, MenuItem>
     */
    public function __invoke(
        int $categoryId,
        bool $includeInactive = false,
        bool $includeArchived = false,
        int $perPage = self::DEFAULT_PER_PAGE,
        int $page = 1,
    ): LengthAwarePaginator {
        $startedAt = microtime(true);
        $branchId = $this->branches->id();

        if ($branchId === null) {
            $exception = MenuDomainException::branchContextRequired();
            $this->logDomainFailure('menu.items.paginate', $exception, $startedAt, [
                'category_id' => $categoryId,
            ]);

            throw $exception;
        }

        $perPage = $this->boundedPerPage($perPage);
        $page = max(1, $page);
        $query = MenuItem::query()
            ->when($includeArchived, fn ($query) => $query->withTrashed())
            ->with('category')
            ->where('branch_id', $branchId)
            ->where('category_id', $categoryId)
            ->when(! $includeInactive, fn ($query) => $query->where('active', true));

        /** @var LengthAwarePaginator<int, MenuItem> $items */
        $items = $query
            ->orderBy('sort_order')
            ->orderByRaw($this->localizedNameOrderExpression($query, 'translated_name', app()->getLocale()))
            ->orderBy('id')
            ->paginate($perPage, ['*'], 'page', $page);

        $this->logSuccess('menu.items.paginate', $startedAt, [
            'branch_id' => $branchId,
            'category_id' => $categoryId,
            'include_archived' => $includeArchived,
            'include_inactive' => $includeInactive,
            'item_count' => $items->count(),
            'page' => $page,
            'per_page' => $perPage,
            'total' => $items->total(),
        ]);

        return $items;
    }

    private function boundedPerPage(int $perPage): int
    {
        return min(self::MAX_PER_PAGE, max(1, $perPage));
    }
}
