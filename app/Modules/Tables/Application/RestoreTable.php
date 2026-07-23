<?php

declare(strict_types=1);

namespace App\Modules\Tables\Application;

use App\Modules\Tables\Domain\TablesDomainException;
use App\Modules\Tables\Infrastructure\Models\Hall;
use App\Modules\Tables\Infrastructure\Models\Table;
use App\Modules\Tenancy\Contracts\BranchContext;
use Illuminate\Support\Facades\DB;

final class RestoreTable
{
    use RecordsTableAction;

    public function __construct(
        private readonly BranchContext $branches,
    ) {}

    public function __invoke(int $hallId, int $tableId): void
    {
        $startedAt = microtime(true);
        $branchId = $this->branchIdOrFail('tables.tables.restore', $startedAt, [
            'hall_id' => $hallId,
            'table_id' => $tableId,
        ]);

        $hall = Hall::withTrashed()
            ->where('branch_id', $branchId)
            ->findOrFail($hallId);

        if ($hall->trashed()) {
            $exception = TablesDomainException::restoreHallFirst();
            $this->logDomainFailure('tables.tables.restore', $exception, $startedAt, [
                'hall_id' => $hallId,
                'table_id' => $tableId,
            ]);

            throw $exception;
        }

        $table = Table::withTrashed()
            ->where('branch_id', $branchId)
            ->where('hall_id', $hallId)
            ->findOrFail($tableId);
        $before = $this->tableAuditPayload($table);

        DB::transaction(function () use ($before, $table, $tableId): void {
            $table->forceFill([
                'deleted_at' => null,
                'archived_with_hall_id' => null,
                'updated_at' => now(),
            ])->save();

            $this->auditTableMutation(
                'tables.table.restored',
                'tables_table',
                $tableId,
                $before,
                $this->tableAuditPayload($table->refresh()),
            );
        });

        $this->logSuccess('tables.tables.restore', $startedAt, [
            'branch_id' => $branchId,
            'hall_id' => $hallId,
            'table_id' => $tableId,
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function branchIdOrFail(string $action, float $startedAt, array $context): int
    {
        $branchId = $this->branches->id();

        if ($branchId !== null) {
            return $branchId;
        }

        $exception = TablesDomainException::branchContextRequired();
        $this->logDomainFailure($action, $exception, $startedAt, $context);

        throw $exception;
    }
}
