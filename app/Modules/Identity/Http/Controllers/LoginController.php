<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Application\AuthenticateUser;
use App\Modules\Identity\Http\Requests\LoginRequest;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Support\Logging\LogContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Throwable;

final class LoginController
{
    public function __construct(
        private readonly TenantResolver $tenants,
        private readonly BranchContext $branches,
    ) {}

    public function create(): View
    {
        return view('modules.identity.login');
    }

    public function store(LoginRequest $request, AuthenticateUser $authenticate): RedirectResponse
    {
        $user = $authenticate(
            (string) $request->string('tenant_slug'),
            (string) $request->string('email'),
            (string) $request->string('password'),
        );

        if ($user === null) {
            $request->session()->forget(['tenant_id', 'branch_id']);

            return back()
                ->withErrors(['email' => __('auth.failed')])
                ->onlyInput('tenant_slug', 'email');
        }

        try {
            $request->session()->forget('branch_id');
            $request->session()->regenerate();
            $request->session()->put('tenant_id', (int) $user->tenant_id);
        } catch (Throwable $exception) {
            $this->clearLoginState($request);

            throw $exception;
        }

        return redirect()->intended(route('admin.dashboard'));
    }

    private function clearLoginState(Request $request): void
    {
        Auth::logout();
        $request->session()->forget(['tenant_id', 'branch_id']);
        $this->branches->clear();
        $this->tenants->clear();
        LogContext::refreshRuntimeContext('identity');
    }
}
