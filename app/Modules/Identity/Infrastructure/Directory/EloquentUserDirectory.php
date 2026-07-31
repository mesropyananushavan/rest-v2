<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Directory;

use App\Modules\Identity\Contracts\BranchAssignableUser;
use App\Modules\Identity\Contracts\UserDirectory;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Identity\Infrastructure\Models\UserBranchAssignment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;

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
        $users = $this->activeBranchUsersWithEffectivePermission($branchId, $permissionCode)
            ->orderBy('users.name')
            ->orderBy('users.id')
            ->get(['users.id', 'users.name'])
            ->map(fn (User $user): BranchAssignableUser => $this->branchAssignableUser($user))
            ->all();

        return array_values($users);
    }

    public function isActiveUserAssignedToBranchWithPermission(int $userId, int $branchId, string $permissionCode): bool
    {
        return $this->activeBranchUsersWithEffectivePermission($branchId, $permissionCode)
            ->where('users.id', $userId)
            ->exists();
    }

    /**
     * @return Builder<User>
     */
    private function activeBranchUsersWithEffectivePermission(int $branchId, string $permissionCode): Builder
    {
        return User::query()
            ->join('user_branch_assignments', function (JoinClause $join): void {
                $join->on('user_branch_assignments.user_id', '=', 'users.id')
                    ->on('user_branch_assignments.tenant_id', '=', 'users.tenant_id');
            })
            ->where('user_branch_assignments.branch_id', $branchId)
            ->where('users.active', true)
            ->where(function (Builder $query) use ($permissionCode): void {
                $query
                    ->where('users.is_superadmin', true)
                    ->orWhereHas('role.permissions', fn (Builder $permissionQuery): Builder => $permissionQuery->where('code', $permissionCode));
            });
    }

    private function branchAssignableUser(User $user): BranchAssignableUser
    {
        $id = $user->getAttribute('id');
        $name = $user->getAttribute('name');

        assert(is_numeric($id));
        assert(is_string($name));

        return new BranchAssignableUser(
            id: (int) $id,
            displayName: $name,
        );
    }
}
