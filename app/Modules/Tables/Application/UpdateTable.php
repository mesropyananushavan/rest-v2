<?php

declare(strict_types=1);

namespace App\Modules\Tables\Application;

use App\Modules\Tables\Domain\TablesDomainException;
use App\Modules\Tables\Infrastructure\Models\Hall;
use App\Modules\Tables\Infrastructure\Models\Table;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Support\I18n\LocalizedText;
use Illuminate\Support\Facades\DB;

final class UpdateTable
{
    use RecordsTableAction;

    public function __construct(
        private readonly BranchContext $branches,
    ) {}

    public function __invoke(
        int $hallId,
        int $tableId,
        LocalizedText $name,
        string $type,
        string $shape,
        ?int $hdmDepartment,
        bool $isDelivery,
        int $sortOrder,
        bool $active,
    ): Table {
        $startedAt = microtime(true);
        $branchId = $this->branchIdOrFail('tables.tables.update', $startedAt, [
            'hall_id' => $hallId,
            'table_id' => $tableId,
        ]);

        Hall::query()
            ->where('branch_id', $branchId)
            ->findOrFail($hallId);

        $table = Table::query()
            ->where('branch_id', $branchId)
            ->where('hall_id', $hallId)
            ->findOrFail($tableId);
        $before = $this->tableAuditPayload($table);

        DB::transaction(function () use ($active, $before, $hdmDepartment, $isDelivery, $name, $shape, $sortOrder, $table, $type): void {
            $table->update([
                'translated_name' => $name->toArray(),
                'type' => $type,
                'shape' => $shape,
                'hdm_department' => $hdmDepartment,
                'is_delivery' => $isDelivery,
                'sort_order' => $sortOrder,
                'active' => $active,
            ]);

            $this->auditTableMutation(
                'tables.table.updated',
                'tables_table',
                (int) $table->id,
                $before,
                $this->tableAuditPayload($table->refresh()),
            );
        });

        $this->logSuccess('tables.tables.update', $startedAt, [
            'branch_id' => $branchId,
            'hall_id' => $hallId,
            'table_id' => (int) $table->id,
        ]);

        return $table;
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
