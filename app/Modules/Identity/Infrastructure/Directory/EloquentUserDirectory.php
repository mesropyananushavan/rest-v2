<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Directory;

use App\Modules\Identity\Contracts\BranchAssignableUser;
use App\Modules\Identity\Contracts\UserDirectory;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Identity\Infrastructure\Models\UserBranchAssignment;
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
        $users = UserBranchAssignment::query()
            ->join('users', function (JoinClause $join): void {
                $join->on('users.id', '=', 'user_branch_assignments.user_id')
                    ->on('users.tenant_id', '=', 'user_branch_assignments.tenant_id');
            })
            ->join('roles', function (JoinClause $join): void {
                $join->on('roles.id', '=', 'users.role_id')
                    ->on('roles.tenant_id', '=', 'user_branch_assignments.tenant_id');
            })
            ->join('role_permissions', function (JoinClause $join): void {
                $join->on('role_permissions.role_id', '=', 'roles.id')
                    ->on('role_permissions.tenant_id', '=', 'user_branch_assignments.tenant_id');
            })
            ->join('permissions', function (JoinClause $join): void {
                $join->on('permissions.id', '=', 'role_permissions.permission_id')
                    ->on('permissions.tenant_id', '=', 'user_branch_assignments.tenant_id');
            })
            ->where('user_branch_assignments.branch_id', $branchId)
            ->where('users.active', true)
            ->where('permissions.code', $permissionCode)
            ->orderBy('users.name')
            ->orderBy('users.id')
            ->get(['users.id', 'users.name'])
            ->map(fn (UserBranchAssignment $assignment): BranchAssignableUser => $this->branchAssignableUser($assignment))
            ->all();

        return array_values($users);
    }

    public function isActiveUserAssignedToBranchWithPermission(int $userId, int $branchId, string $permissionCode): bool
    {
        return UserBranchAssignment::query()
            ->join('users', function (JoinClause $join): void {
                $join->on('users.id', '=', 'user_branch_assignments.user_id')
                    ->on('users.tenant_id', '=', 'user_branch_assignments.tenant_id');
            })
            ->join('roles', function (JoinClause $join): void {
                $join->on('roles.id', '=', 'users.role_id')
                    ->on('roles.tenant_id', '=', 'user_branch_assignments.tenant_id');
            })
            ->join('role_permissions', function (JoinClause $join): void {
                $join->on('role_permissions.role_id', '=', 'roles.id')
                    ->on('role_permissions.tenant_id', '=', 'user_branch_assignments.tenant_id');
            })
            ->join('permissions', function (JoinClause $join): void {
                $join->on('permissions.id', '=', 'role_permissions.permission_id')
                    ->on('permissions.tenant_id', '=', 'user_branch_assignments.tenant_id');
            })
            ->where('user_branch_assignments.user_id', $userId)
            ->where('user_branch_assignments.branch_id', $branchId)
            ->where('users.active', true)
            ->where('permissions.code', $permissionCode)
            ->exists();
    }

    private function branchAssignableUser(UserBranchAssignment $assignment): BranchAssignableUser
    {
        $id = $assignment->getAttribute('id');
        $name = $assignment->getAttribute('name');

        assert(is_numeric($id));
        assert(is_string($name));

        return new BranchAssignableUser(
            id: (int) $id,
            displayName: $name,
        );
    }
}
