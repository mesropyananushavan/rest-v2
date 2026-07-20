<?php

declare(strict_types=1);

namespace App\Modules\Identity\Contracts;

interface UserDirectory
{
    public function findName(int $userId): ?string;

    public function firstAssignedBranchId(int $userId): ?int;

    /**
     * @return list<int>
     */
    public function assignedBranchIds(int $userId): array;
}
