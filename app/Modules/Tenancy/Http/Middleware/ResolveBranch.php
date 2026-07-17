<?php

namespace App\Modules\Tenancy\Http\Middleware;

use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Modules\Tenancy\Infrastructure\Models\Branch;
use App\Support\Logging\LogContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ResolveBranch
{
    public function __construct(
        private readonly TenantResolver $tenants,
        private readonly BranchContext $branches,
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
        $header = $request->headers->get('X-Branch-ID');

        if (is_numeric($header)) {
            return (int) $header;
        }

        if ($request->session()->has('branch_id')) {
            $sessionBranchId = $request->session()->get('branch_id');

            return is_numeric($sessionBranchId) ? (int) $sessionBranchId : null;
        }

        return null;
    }
}
