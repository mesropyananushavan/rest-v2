<?php

declare(strict_types=1);

namespace App\Http;

use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Modules\Tenancy\Infrastructure\Models\Branch;
use App\Modules\Tenancy\Infrastructure\Models\Tenant;
use Illuminate\View\View;

final class AdminShellComposer
{
    public function __construct(
        private readonly TenantResolver $tenants,
        private readonly BranchContext $branches,
    ) {}

    public function compose(View $view): void
    {
        $tenantId = $this->tenants->id();
        $branchId = $this->branches->id();

        $tenant = $tenantId === null ? null : Tenant::query()->find($tenantId);
        $branch = $branchId === null ? null : Branch::query()->find($branchId);

        $view->with('adminShell', [
            'tenant_name' => is_string($tenant?->name) ? $tenant->name : null,
            'branch_name' => is_string($branch?->name) ? $branch->name : null,
        ]);
    }
}
