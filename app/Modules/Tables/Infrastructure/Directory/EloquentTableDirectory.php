<?php

declare(strict_types=1);

namespace App\Modules\Tables\Infrastructure\Directory;

use App\Modules\Tables\Contracts\TableDirectory;
use App\Modules\Tables\Contracts\TableSummary;
use App\Modules\Tables\Infrastructure\Models\Table;

final class EloquentTableDirectory implements TableDirectory
{
    public function findActiveInBranch(int $tableId, int $branchId): ?TableSummary
    {
        $table = Table::query()
            ->where('branch_id', $branchId)
            ->where('active', true)
            ->find($tableId);

        if (! $table instanceof Table) {
            return null;
        }

        return new TableSummary(
            id: (int) $table->id,
            branchId: (int) $table->branch_id,
        );
    }
}
