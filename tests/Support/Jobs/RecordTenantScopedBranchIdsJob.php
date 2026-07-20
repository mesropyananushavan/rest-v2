<?php

declare(strict_types=1);

namespace Tests\Support\Jobs;

use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Modules\Tenancy\Infrastructure\Models\Branch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;

final readonly class RecordTenantScopedBranchIdsJob implements ShouldQueue
{
    public function __construct(private string $cacheKey) {}

    public function handle(): void
    {
        Cache::put($this->cacheKey, [
            'tenant_id' => app(TenantResolver::class)->id(),
            'branch_id' => app(BranchContext::class)->id(),
            'visible_branch_ids' => Branch::query()
                ->orderBy('id')
                ->pluck('id')
                ->map(fn (mixed $id): int => (int) $id)
                ->all(),
        ]);
    }
}
