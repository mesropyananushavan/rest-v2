<?php

declare(strict_types=1);

namespace App\Http;

use App\Modules\Identity\Contracts\UserDirectory;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantDirectory;
use App\Modules\Tenancy\Contracts\TenantResolver;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

final class AdminShellComposer
{
    public function __construct(
        private readonly TenantResolver $tenants,
        private readonly BranchContext $branches,
        private readonly UserDirectory $users,
        private readonly TenantDirectory $tenantDirectory,
    ) {}

    public function compose(View $view): void
    {
        $tenantId = $this->tenants->id();
        $branchId = $this->branches->id();

        $branchOptions = $this->branchOptions();
        $currentBranch = collect($branchOptions)
            ->first(fn (array $branch): bool => $branch['id'] === $branchId);

        $view->with('adminShell', [
            'tenant_name' => $tenantId === null ? null : $this->tenantDirectory->tenantName($tenantId),
            'branch_name' => $currentBranch['name'] ?? null,
            'branch_id' => $branchId,
            'branch_options' => $branchOptions,
            'locale' => app()->getLocale(),
        ]);
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function branchOptions(): array
    {
        $userId = Auth::id();

        if (! is_numeric($userId)) {
            return [];
        }

        return $this->tenantDirectory->branchSummariesForIds(
            $this->users->assignedBranchIds((int) $userId),
        );
    }
}
