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
            (string) $request->string('email'),
            (string) $request->string('password'),
        );

        if ($user === null) {
            return back()
                ->withErrors(['email' => __('auth.failed')])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended('/');
    }
}
