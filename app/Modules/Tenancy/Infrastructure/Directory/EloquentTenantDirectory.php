<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Infrastructure\Directory;

use App\Modules\Tenancy\Contracts\TenantDirectory;
use App\Modules\Tenancy\Infrastructure\Models\Branch;
use App\Modules\Tenancy\Infrastructure\Models\Tenant;

final class EloquentTenantDirectory implements TenantDirectory
{
    public function activeTenantIds(): array
    {
        /** @var list<int|string> $ids */
        $ids = array_values(Tenant::query()
            ->where('status', 'active')
            ->orderBy('id')
            ->pluck('id')
            ->all());

        return array_map(fn (int|string $id): int => (int) $id, $ids);
    }

    public function tenantName(int $tenantId): ?string
    {
        $name = Tenant::query()
            ->whereKey($tenantId)
            ->value('name');

        return is_string($name) ? $name : null;
    }

    public function branchSummariesForIds(array $branchIds): array
    {
        $uniqueBranchIds = array_values(array_unique($branchIds));

        if ($uniqueBranchIds === []) {
            return [];
        }

        $branches = Branch::query()
            ->whereIn('id', $uniqueBranchIds)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Branch $branch): array => [
                'id' => (int) $branch->id,
                'name' => (string) $branch->name,
            ])
            ->values()
            ->all();

        return array_values($branches);
    }
}
