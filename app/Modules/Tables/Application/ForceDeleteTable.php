<?php

declare(strict_types=1);

namespace App\Modules\Tables\Application;

use App\Modules\Tables\Domain\TablesDomainException;
use App\Modules\Tables\Infrastructure\Models\Table;
use App\Modules\Tenancy\Contracts\BranchContext;
use Illuminate\Support\Facades\DB;

final class ForceDeleteTable
{
    use RecordsTableAction;

    public function __construct(
        private readonly BranchContext $branches,
    ) {}

    public function __invoke(int $hallId, int $tableId): void
    {
        $startedAt = microtime(true);
        $branchId = $this->branchIdOrFail('tables.tables.force_delete', $startedAt, [
            'hall_id' => $hallId,
            'table_id' => $tableId,
        ]);

        $table = Table::onlyTrashed()
            ->where('branch_id', $branchId)
            ->where('hall_id', $hallId)
            ->findOrFail($tableId);
        $before = $this->tableAuditPayload($table);

        DB::transaction(function () use ($before, $table, $tableId): void {
            $table->forceDelete();

            $this->auditTableMutation('tables.table.permanently_deleted', 'tables_table', $tableId, $before, [
                'deleted' => true,
            ]);
        });

        $this->logSuccess('tables.tables.force_delete', $startedAt, [
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
