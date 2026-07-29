<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Middleware;

use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantDirectory;
use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Support\Api\ApiResponse;
use App\Support\Logging\Redactor;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

final class EnsureTenantIsServiceable
{
    public function __construct(
        private readonly TenantDirectory $tenants,
        private readonly TenantResolver $tenantResolver,
        private readonly BranchContext $branches,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() === null) {
            $response = $next($request);

            assert($response instanceof Response);

            return $response;
        }

        $tenantId = $this->tenantResolver->id();

        if ($tenantId === null || $this->tenants->isServiceable($tenantId)) {
            $response = $next($request);

            assert($response instanceof Response);

            return $response;
        }

        $this->logBlockedTenant($tenantId);

        if ($request->expectsJson() || $request->is('api/*')) {
            return ApiResponse::error($request, 'tenant.suspended', __('api.errors.tenant_suspended'), null, 403);
        }

        Auth::logout();
        $this->branches->clear();
        $this->tenantResolver->clear();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->withErrors(['email' => __('auth.tenant_suspended')]);
    }

    private function logBlockedTenant(int $tenantId): void
    {
        Log::warning('tenant service blocked', Redactor::context([
            'tenant_id' => $tenantId,
            'reason_code' => 'tenant_not_serviceable',
        ]));
    }
}
