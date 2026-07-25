<?php

declare(strict_types=1);

namespace App\Modules\Tables\Contracts;

interface TableDirectory
{
    public function findActiveInBranch(int $tableId, int $branchId): ?TableSummary;
}
