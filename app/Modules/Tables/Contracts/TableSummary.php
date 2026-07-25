<?php

declare(strict_types=1);

namespace App\Modules\Tables\Contracts;

final readonly class TableSummary
{
    public function __construct(
        public int $id,
        public int $branchId,
    ) {}
}
