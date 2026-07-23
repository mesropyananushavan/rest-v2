<?php

declare(strict_types=1);

namespace App\Modules\Tables\Application;

use App\Modules\Tables\Domain\TablesDomainException;
use App\Modules\Tables\Infrastructure\Models\Hall;
use App\Modules\Tables\Infrastructure\Models\Table;
use App\Modules\Tenancy\Contracts\BranchContext;
use Illuminate\Support\Facades\DB;

final class RestoreHall
{
    use RecordsTableAction;

    public function __construct(
        private readonly BranchContext $branches,
    ) {}

    public function __invoke(int $hallId): void
    {
        $startedAt = microtime(true);
        $branchId = $this->branches->id();
        $restoredTableCount = 0;

        if ($branchId === null) {
            $exception = TablesDomainException::branchContextRequired();
            $this->logDomainFailure('tables.halls.restore', $exception, $startedAt, [
                'hall_id' => $hallId,
            ]);

            throw $exception;
        }

        $hall = Hall::withTrashed()
            ->where('branch_id', $branchId)
            ->findOrFail($hallId);
        $before = $this->hallAuditPayload($hall);

        DB::transaction(function () use (&$restoredTableCount, $before, $branchId, $hall, $hallId): void {
            $restoredAt = now();

            $hall->forceFill([
                'deleted_at' => null,
                'updated_at' => $restoredAt,
            ])->save();

            $restoredTableCount = Table::withTrashed()
                ->where('branch_id', $branchId)
                ->where('hall_id', $hallId)
                ->where('archived_with_hall_id', $hallId)
                ->update([
                    'deleted_at' => null,
                    'archived_with_hall_id' => null,
                    'updated_at' => $restoredAt,
                ]);

            $this->auditTableMutation(
                'tables.hall.restored',
                'tables_hall',
                $hallId,
                $before,
                $this->hallAuditPayload($hall->refresh()) + [
                    'cascade' => [
                        'marker_hall_id' => $hallId,
                        'restored_table_count' => $restoredTableCount,
                    ],
                ],
            );
        });

        $this->logSuccess('tables.halls.restore', $startedAt, [
            'branch_id' => $branchId,
            'hall_id' => $hallId,
            'restored_table_count' => $restoredTableCount,
        ]);
    }
}
