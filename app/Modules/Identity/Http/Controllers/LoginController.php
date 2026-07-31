<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Application\AuthenticateUser;
use App\Modules\Identity\Http\Requests\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class LoginController
{
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

        $request->session()->regenerate();
        $request->session()->put('tenant_id', (int) $user->tenant_id);

        return redirect()->intended(route('admin.dashboard'));
    }
}
