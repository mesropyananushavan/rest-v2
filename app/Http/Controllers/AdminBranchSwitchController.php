<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Identity\Contracts\UserDirectory;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantDirectory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class AdminBranchSwitchController
{
    public function __construct(
        private readonly UserDirectory $users,
        private readonly TenantDirectory $tenants,
        private readonly BranchContext $branches,
    ) {}

    public function __invoke(Request $request): RedirectResponse
    {
        /** @var array{branch_id: int|string} $validated */
        $validated = $request->validate([
            'branch_id' => ['required', 'integer'],
        ]);

        $userId = $request->user()?->getAuthIdentifier();
        $branchId = (int) $validated['branch_id'];

        if (! is_numeric($userId)) {
            abort(403);
        }

        $assignedBranchIds = $this->users->assignedBranchIds((int) $userId);

        if (! in_array($branchId, $assignedBranchIds, true)) {
            abort(404);
        }

        if ($this->tenants->branchSummariesForIds([$branchId]) === []) {
            abort(404);
        }

        $this->branches->set($branchId);
        $request->session()->put('branch_id', $branchId);

        return back()->with('status', __('admin.flash.branch_updated'));
    }
}
