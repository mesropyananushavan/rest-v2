<?php

declare(strict_types=1);

namespace App\Modules\Menu\Application;

use App\Modules\Menu\Application\Concerns\FiltersLocalizedNames;
use App\Modules\Menu\Domain\MenuDomainException;
use App\Modules\Menu\Infrastructure\Models\MenuItem;
use App\Modules\Tenancy\Contracts\BranchContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final class SearchMenuItems
{
    use FiltersLocalizedNames;
    use RecordsMenuAction;

    private const DEFAULT_PER_PAGE = 25;

    private const MAX_PER_PAGE = 50;

    public function __construct(
        private readonly BranchContext $branches,
    ) {}

    /**
     * @param  'active'|'archived'|'all'  $archiveMode
     * @return LengthAwarePaginator<int, MenuItem>
     */
    public function __invoke(
        string $search,
        bool $includeInactive = false,
        string $archiveMode = 'active',
        int $perPage = self::DEFAULT_PER_PAGE,
        int $page = 1,
    ): LengthAwarePaginator {
        $startedAt = microtime(true);
        $branchId = $this->branches->id();

        if ($branchId === null) {
            $exception = MenuDomainException::branchContextRequired();
            $this->logDomainFailure('menu.items.search', $exception, $startedAt);

            throw $exception;
        }

        $perPage = $this->boundedPerPage($perPage);
        $page = max(1, $page);
        $normalizedSearch = $this->normalizedSearch($search);

        if ($normalizedSearch === null) {
            /** @var LengthAwarePaginator<int, MenuItem> $emptyItems */
            $emptyItems = new LengthAwarePaginator([], 0, $perPage, $page, [
                'pageName' => 'page',
                'path' => LengthAwarePaginator::resolveCurrentPath(),
            ]);

            $this->logSuccess('menu.items.search', $startedAt, [
                'archive_mode' => $archiveMode,
                'branch_id' => $branchId,
                'include_inactive' => $includeInactive,
                'item_count' => 0,
                'page' => $page,
                'per_page' => $perPage,
                'search_present' => false,
                'total' => 0,
            ]);

            return $emptyItems;
        }

        $query = MenuItem::query()
            ->with('category')
            ->where('branch_id', $branchId)
            ->when(! $includeInactive, fn ($query) => $query->where('active', true));
        $this->applyArchiveMode($query, $archiveMode);

        $this->filterLocalizedName($query, 'translated_name', $normalizedSearch);

        /** @var LengthAwarePaginator<int, MenuItem> $items */
        $items = $query
            ->orderBy('sort_order')
            ->orderByRaw($this->localizedNameOrderExpression($query, 'translated_name', app()->getLocale()))
            ->orderBy('id')
            ->paginate($perPage, ['*'], 'page', $page);

        $this->logSuccess('menu.items.search', $startedAt, [
            'archive_mode' => $archiveMode,
            'branch_id' => $branchId,
            'include_inactive' => $includeInactive,
            'item_count' => $items->count(),
            'page' => $page,
            'per_page' => $perPage,
            'search_present' => true,
            'total' => $items->total(),
        ]);

        return $items;
    }

    private function boundedPerPage(int $perPage): int
    {
        return min(self::MAX_PER_PAGE, max(1, $perPage));
    }

    /**
     * @param  Builder<MenuItem>  $query
     * @param  'active'|'archived'|'all'  $archiveMode
     */
    private function applyArchiveMode(Builder $query, string $archiveMode): void
    {
        match ($archiveMode) {
            'active' => null,
            'archived' => $query->onlyTrashed(),
            'all' => $query->withTrashed(),
        };
    }
}
