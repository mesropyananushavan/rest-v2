<?php

declare(strict_types=1);

namespace App\Modules\Tables\Application;

use App\Modules\Tables\Domain\TablesDomainException;
use App\Modules\Tables\Infrastructure\Models\Hall;
use App\Modules\Tables\Infrastructure\Models\Table;
use App\Modules\Tenancy\Contracts\BranchContext;
use Illuminate\Support\Facades\DB;

final class ArchiveHall
{
    use RecordsTableAction;

    public function __construct(
        private readonly BranchContext $branches,
    ) {}

    public function __invoke(int $hallId): void
    {
        $startedAt = microtime(true);
        $branchId = $this->branches->id();
        $archivedTableCount = 0;

        if ($branchId === null) {
            $exception = TablesDomainException::branchContextRequired();
            $this->logDomainFailure('tables.halls.archive', $exception, $startedAt, [
                'hall_id' => $hallId,
            ]);

            throw $exception;
        }

        $hall = Hall::query()
            ->where('branch_id', $branchId)
            ->findOrFail($hallId);
        $before = $this->hallAuditPayload($hall);

        DB::transaction(function () use (&$archivedTableCount, $before, $branchId, $hall, $hallId): void {
            $archivedAt = now();

            $archivedTableCount = Table::query()
                ->where('branch_id', $branchId)
                ->where('hall_id', $hallId)
                ->whereNull('deleted_at')
                ->whereNull('archived_with_hall_id')
                ->update([
                    'deleted_at' => $archivedAt,
                    'archived_with_hall_id' => $hallId,
                    'updated_at' => $archivedAt,
                ]);

            $hall->forceFill([
                'deleted_at' => $archivedAt,
                'updated_at' => $archivedAt,
            ])->save();

            $this->auditTableMutation(
                'tables.hall.archived',
                'tables_hall',
                $hallId,
                $before,
                $this->hallAuditPayload($hall->refresh()) + [
                    'cascade' => [
                        'archived_table_count' => $archivedTableCount,
                        'marker_hall_id' => $hallId,
                    ],
                ],
            );
        });

        $this->logSuccess('tables.halls.archive', $startedAt, [
            'branch_id' => $branchId,
            'hall_id' => $hallId,
            'archived_table_count' => $archivedTableCount,
        ]);
    }
}
