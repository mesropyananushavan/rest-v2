<?php

declare(strict_types=1);

namespace App\Modules\Tables\Contracts;

interface HallLayoutReader
{
    /**
     * @return list<HallLayout>
     */
    public function layoutForBranch(int $branchId): array;
}
