<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Middleware;

use App\Modules\Identity\Contracts\UserDirectory;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Modules\Tenancy\Infrastructure\Models\Branch;
use App\Support\Logging\LogContext;
use App\Support\Logging\Redactor;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

final class ResolveBranch
{
    public function __construct(
        private readonly TenantResolver $tenants,
        private readonly BranchContext $branches,
        private readonly UserDirectory $users,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $branchId = $this->branchIdFrom($request);

        if ($branchId !== null) {
            $branch = Branch::query()->findOrFail($branchId);

            if ($this->tenants->id() !== null && (int) $branch->tenant_id !== $this->tenants->id()) {
                abort(404);
            }

            $this->branches->set((int) $branch->id);
            $request->session()->put('branch_id', (int) $branch->id);
            LogContext::refreshRuntimeContext();
        }

        $response = $next($request);

        assert($response instanceof Response);

        return $response;
    }

    private function branchIdFrom(Request $request): ?int
    {
        $userId = $this->authenticatedUserId($request);
        $assignedBranchIds = null;
        $header = $request->headers->get('X-Branch-ID');

        if (is_numeric($header)) {
            if (App::environment('production')) {
                $this->logRejectedBranchCandidate('header', 'branch_header_disabled_in_production');
            } else {
                return $this->authorizedCandidate(
                    request: $request,
                    branchId: (int) $header,
                    source: 'header',
                    userId: $userId,
                    assignedBranchIds: $assignedBranchIds,
                );
            }
        }

        if ($request->session()->has('branch_id')) {
            $sessionBranchId = $request->session()->get('branch_id');

            if (is_numeric($sessionBranchId)) {
                $candidate = $this->authorizedCandidate(
                    request: $request,
                    branchId: (int) $sessionBranchId,
                    source: 'session',
                    userId: $userId,
                    assignedBranchIds: $assignedBranchIds,
                );

                if ($candidate !== null) {
                    return $candidate;
                }
            } else {
                $request->session()->forget('branch_id');
                $this->logRejectedBranchCandidate('session', 'branch_id_not_numeric');
            }
        }

        if ($userId !== null) {
            return $this->assignedBranchIds($userId, $assignedBranchIds)[0] ?? null;
        }

        return null;
    }

    /**
     * @param  list<int>|null  $assignedBranchIds
     */
    private function authorizedCandidate(
        Request $request,
        int $branchId,
        string $source,
        ?int $userId,
        ?array &$assignedBranchIds,
    ): ?int {
        if ($userId === null) {
            return $branchId;
        }

        if (in_array($branchId, $this->assignedBranchIds($userId, $assignedBranchIds), true)) {
            return $branchId;
        }

        $this->logRejectedBranchCandidate($source, 'branch_not_assigned');

        if ($source === 'session') {
            $request->session()->forget('branch_id');

            return null;
        }

        abort(404);
    }

    /**
     * @param  list<int>|null  $assignedBranchIds
     *
     * @param-out list<int> $assignedBranchIds
     *
     * @return list<int>
     */
    private function assignedBranchIds(int $userId, ?array &$assignedBranchIds): array
    {
        if ($assignedBranchIds === null) {
            $assignedBranchIds = $this->users->assignedBranchIds($userId);
        }

        return $assignedBranchIds;
    }

    private function authenticatedUserId(Request $request): ?int
    {
        $userId = $request->user()?->getAuthIdentifier();

        return is_numeric($userId) ? (int) $userId : null;
    }

    private function logRejectedBranchCandidate(string $source, string $reasonCode): void
    {
        Log::warning('branch candidate rejected', Redactor::context([
            'source' => $source,
            'reason_code' => $reasonCode,
        ]));
    }
}
