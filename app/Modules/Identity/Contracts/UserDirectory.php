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

    /**
     * @return list<BranchAssignableUser>
     */
    public function activeUsersAssignedToBranchWithPermission(int $branchId, string $permissionCode): array;

    public function isActiveUserAssignedToBranchWithPermission(int $userId, int $branchId, string $permissionCode): bool;
}
