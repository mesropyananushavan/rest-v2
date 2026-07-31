<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Directory;

use App\Modules\Identity\Contracts\BranchAssignableUser;
use App\Modules\Identity\Contracts\UserDirectory;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Identity\Infrastructure\Models\UserBranchAssignment;

final class EloquentUserDirectory implements UserDirectory
{
    public function findName(int $userId): ?string
    {
        return User::query()->find($userId)?->name;
    }

    public function firstAssignedBranchId(int $userId): ?int
    {
        $branchId = UserBranchAssignment::query()
            ->where('user_id', $userId)
            ->orderBy('id')
            ->value('branch_id');

        return is_numeric($branchId) ? (int) $branchId : null;
    }

    public function assignedBranchIds(int $userId): array
    {
        /** @var list<int|string> $branchIds */
        $branchIds = array_values(UserBranchAssignment::query()
            ->where('user_id', $userId)
            ->orderBy('id')
            ->pluck('branch_id')
            ->all());

        return array_map(fn (int|string $branchId): int => (int) $branchId, $branchIds);
    }

    public function activeUsersAssignedToBranchWithPermission(int $branchId, string $permissionCode): array
    {
        return User::query()
            ->where('active', true)
            ->whereHas('branchAssignments', fn ($query) => $query->where('branch_id', $branchId))
            ->whereHas('role.permissions', fn ($query) => $query->where('permissions.code', $permissionCode))
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name'])
            ->map(fn (User $user): BranchAssignableUser => new BranchAssignableUser(
                id: (int) $user->id,
                displayName: (string) $user->name,
            ))
            ->values()
            ->all();
    }

    public function isActiveUserAssignedToBranchWithPermission(int $userId, int $branchId, string $permissionCode): bool
    {
        return User::query()
            ->whereKey($userId)
            ->where('active', true)
            ->whereHas('branchAssignments', fn ($query) => $query->where('branch_id', $branchId))
            ->whereHas('role.permissions', fn ($query) => $query->where('permissions.code', $permissionCode))
            ->exists();
    }
}
