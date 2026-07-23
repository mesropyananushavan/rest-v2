<?php

declare(strict_types=1);

namespace App\Modules\Tables\Application;

use App\Modules\Tables\Domain\TablesDomainException;
use App\Modules\Tables\Infrastructure\Models\Table;
use App\Modules\Tenancy\Contracts\BranchContext;
use Illuminate\Support\Facades\DB;

final class ArchiveTable
{
    use RecordsTableAction;

    public function __construct(
        private readonly BranchContext $branches,
    ) {}

    public function __invoke(int $hallId, int $tableId): void
    {
        $startedAt = microtime(true);
        $branchId = $this->branchIdOrFail('tables.tables.archive', $startedAt, [
            'hall_id' => $hallId,
            'table_id' => $tableId,
        ]);

        $table = Table::query()
            ->where('branch_id', $branchId)
            ->where('hall_id', $hallId)
            ->findOrFail($tableId);
        $before = $this->tableAuditPayload($table);

        DB::transaction(function () use ($before, $table, $tableId): void {
            $table->forceFill(['archived_with_hall_id' => null])->save();
            $table->delete();

            $this->auditTableMutation(
                'tables.table.archived',
                'tables_table',
                $tableId,
                $before,
                $this->tableAuditPayload($table->refresh()),
            );
        });

        $this->logSuccess('tables.tables.archive', $startedAt, [
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
