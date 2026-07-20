<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Infrastructure\Directory;

use App\Modules\Tenancy\Contracts\TenantDirectory;
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
}
