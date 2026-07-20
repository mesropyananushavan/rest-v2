<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Contracts;

interface TenantDirectory
{
    /**
     * @return list<int>
     */
    public function activeTenantIds(): array;

    public function tenantName(int $tenantId): ?string;

    /**
     * @param  list<int>  $branchIds
     * @return list<array{id: int, name: string}>
     */
    public function branchSummariesForIds(array $branchIds): array;
}
