<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Support\Logging\LogContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

final class LogoutController
{
    public function __construct(
        private readonly TenantResolver $tenants,
        private readonly BranchContext $branches,
    ) {}

    public function __invoke(Request $request): RedirectResponse
    {
        LogContext::refreshRuntimeContext('identity');
        Log::info('logout success', ['auth_event' => 'logout_success']);

        Auth::logout();
        $this->branches->clear();
        $this->tenants->clear();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
