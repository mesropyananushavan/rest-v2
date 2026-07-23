<?php

declare(strict_types=1);

namespace App\Modules\Tables\Application;

use App\Modules\Tables\Domain\TablesDomainException;
use App\Modules\Tables\Infrastructure\Models\Hall;
use App\Modules\Tables\Infrastructure\Models\Table;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Support\I18n\LocalizedText;
use Illuminate\Support\Facades\DB;

final class CreateTable
{
    use RecordsTableAction;

    public function __construct(
        private readonly BranchContext $branches,
    ) {}

    public function __invoke(
        int $hallId,
        LocalizedText $name,
        string $type = 'standard',
        string $shape = 'square',
        ?int $hdmDepartment = null,
        bool $isDelivery = false,
        int $sortOrder = 0,
        bool $active = true,
    ): Table {
        $startedAt = microtime(true);
        $branchId = $this->branchIdOrFail('tables.tables.create', $startedAt, [
            'hall_id' => $hallId,
        ]);

        $hall = Hall::query()
            ->where('branch_id', $branchId)
            ->findOrFail($hallId);

        $table = DB::transaction(function () use ($active, $branchId, $hall, $hdmDepartment, $isDelivery, $name, $shape, $sortOrder, $type): Table {
            $table = Table::query()->create([
                'branch_id' => $branchId,
                'hall_id' => (int) $hall->id,
                'translated_name' => $name->toArray(),
                'type' => $type,
                'shape' => $shape,
                'hdm_department' => $hdmDepartment,
                'is_delivery' => $isDelivery,
                'sort_order' => $sortOrder,
                'active' => $active,
            ]);

            $this->auditTableMutation(
                'tables.table.created',
                'tables_table',
                (int) $table->id,
                null,
                $this->tableAuditPayload($table),
            );

            return $table;
        });

        $this->logSuccess('tables.tables.create', $startedAt, [
            'branch_id' => $branchId,
            'hall_id' => (int) $hall->id,
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
