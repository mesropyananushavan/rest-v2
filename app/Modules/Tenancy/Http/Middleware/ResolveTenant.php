<?php

namespace App\Modules\Tenancy\Http\Middleware;

use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Modules\Tenancy\Infrastructure\Models\Tenant;
use App\Support\Logging\LogContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

final class ResolveTenant
{
    public function __construct(private readonly TenantResolver $tenants) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenantId = $this->tenantIdFrom($request);

        if ($tenantId !== null) {
            $tenant = Tenant::query()->findOrFail($tenantId);

            $this->tenants->set((int) $tenant->id);
            $request->session()->put('tenant_id', (int) $tenant->id);
            App::setLocale((string) $tenant->default_locale);
            LogContext::refreshRuntimeContext();
        }

        $response = $next($request);

        assert($response instanceof Response);

        return $response;
    }

    private function tenantIdFrom(Request $request): ?int
    {
        $tenantId = data_get($request->user(), 'tenant_id');

        if (is_numeric($tenantId)) {
            return (int) $tenantId;
        }

        if ($request->session()->has('tenant_id')) {
            $sessionTenantId = $request->session()->get('tenant_id');

            return is_numeric($sessionTenantId) ? (int) $sessionTenantId : null;
        }

        if (App::environment('production')) {
            return null;
        }

        $header = $request->headers->get('X-Tenant-ID');

        return is_numeric($header) ? (int) $header : null;
    }
}
