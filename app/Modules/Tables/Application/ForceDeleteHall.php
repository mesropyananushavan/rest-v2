<?php

declare(strict_types=1);

namespace App\Modules\Tables\Application;

use App\Modules\Tables\Domain\TablesDomainException;
use App\Modules\Tables\Infrastructure\Models\Hall;
use App\Modules\Tables\Infrastructure\Models\Table;
use App\Modules\Tenancy\Contracts\BranchContext;
use Illuminate\Support\Facades\DB;

final class ForceDeleteHall
{
    use RecordsTableAction;

    public function __construct(
        private readonly BranchContext $branches,
    ) {}

    public function __invoke(int $hallId): void
    {
        $startedAt = microtime(true);
        $branchId = $this->branches->id();
        $deletedTableCount = 0;

        if ($branchId === null) {
            $exception = TablesDomainException::branchContextRequired();
            $this->logDomainFailure('tables.halls.force_delete', $exception, $startedAt, [
                'hall_id' => $hallId,
            ]);

            throw $exception;
        }

        $hall = Hall::onlyTrashed()
            ->where('branch_id', $branchId)
            ->findOrFail($hallId);
        $before = $this->hallAuditPayload($hall);

        DB::transaction(function () use (&$deletedTableCount, $before, $branchId, $hall, $hallId): void {
            $deletedTableCount = Table::onlyTrashed()
                ->where('branch_id', $branchId)
                ->where('hall_id', $hallId)
                ->count();

            Table::onlyTrashed()
                ->where('branch_id', $branchId)
                ->where('hall_id', $hallId)
                ->forceDelete();

            $hall->forceDelete();

            $this->auditTableMutation('tables.hall.permanently_deleted', 'tables_hall', $hallId, $before, [
                'deleted' => true,
                'cascade' => [
                    'deleted_table_count' => $deletedTableCount,
                ],
            ]);
        });

        $this->logSuccess('tables.halls.force_delete', $startedAt, [
            'branch_id' => $branchId,
            'hall_id' => $hallId,
            'deleted_table_count' => $deletedTableCount,
        ]);
    }
}
