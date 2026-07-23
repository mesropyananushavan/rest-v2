<?php

declare(strict_types=1);

namespace App\Modules\Tables\Application;

use App\Modules\Tables\Domain\TablesDomainException;
use App\Modules\Tables\Infrastructure\Models\Hall;
use App\Modules\Tenancy\Contracts\BranchContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final class PaginateHalls
{
    use RecordsTableAction;

    private const DEFAULT_PER_PAGE = 25;

    private const MAX_PER_PAGE = 50;

    public function __construct(
        private readonly BranchContext $branches,
    ) {}

    /**
     * @param  'active'|'archived'|'all'  $archiveMode
     * @return LengthAwarePaginator<int, Hall>
     */
    public function __invoke(
        bool $includeInactive = false,
        string $archiveMode = 'active',
        int $perPage = self::DEFAULT_PER_PAGE,
        int $page = 1,
    ): LengthAwarePaginator {
        $startedAt = microtime(true);
        $branchId = $this->branches->id();

        if ($branchId === null) {
            $exception = TablesDomainException::branchContextRequired();
            $this->logDomainFailure('tables.halls.paginate', $exception, $startedAt);

            throw $exception;
        }

        $perPage = $this->boundedPerPage($perPage);
        $page = max(1, $page);
        $query = Hall::query()
            ->where('branch_id', $branchId)
            ->when(! $includeInactive, fn (Builder $query): Builder => $query->where('active', true));
        $this->applyArchiveMode($query, $archiveMode);

        /** @var LengthAwarePaginator<int, Hall> $halls */
        $halls = $query
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate($perPage, ['*'], 'page', $page);

        $this->logSuccess('tables.halls.paginate', $startedAt, [
            'archive_mode' => $archiveMode,
            'branch_id' => $branchId,
            'hall_count' => $halls->count(),
            'include_inactive' => $includeInactive,
            'page' => $page,
            'per_page' => $perPage,
            'total' => $halls->total(),
        ]);

        return $halls;
    }

    private function boundedPerPage(int $perPage): int
    {
        return min(self::MAX_PER_PAGE, max(1, $perPage));
    }

    /**
     * @param  Builder<Hall>  $query
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
