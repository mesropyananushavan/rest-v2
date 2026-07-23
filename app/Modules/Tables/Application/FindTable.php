<?php

declare(strict_types=1);

namespace App\Modules\Tables\Application;

use App\Modules\Tables\Domain\TablesDomainException;
use App\Modules\Tables\Infrastructure\Models\Table;
use App\Modules\Tenancy\Contracts\BranchContext;

final class FindTable
{
    use RecordsTableAction;

    public function __construct(
        private readonly BranchContext $branches,
    ) {}

    public function __invoke(int $hallId, int $tableId): Table
    {
        $startedAt = microtime(true);
        $branchId = $this->branches->id();

        if ($branchId === null) {
            $exception = TablesDomainException::branchContextRequired();
            $this->logDomainFailure('tables.tables.find', $exception, $startedAt, [
                'hall_id' => $hallId,
                'table_id' => $tableId,
            ]);

            throw $exception;
        }

        $table = Table::query()
            ->where('branch_id', $branchId)
            ->where('hall_id', $hallId)
            ->findOrFail($tableId);

        $this->logSuccess('tables.tables.find', $startedAt, [
            'branch_id' => $branchId,
            'hall_id' => $hallId,
            'table_id' => $tableId,
        ]);

        return $table;
    }
}
