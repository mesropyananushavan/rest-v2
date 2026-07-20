<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Validation\Rule;

final class AdminLocaleSwitchController
{
    public function __invoke(Request $request): RedirectResponse
    {
        /** @var array{locale: string} $validated */
        $validated = $request->validate([
            'locale' => ['required', 'string', Rule::in(['hy', 'ru', 'en'])],
        ]);

        $request->session()->put('locale', $validated['locale']);
        App::setLocale($validated['locale']);

        return back()->with('status', __('admin.flash.locale_updated'));
    }
}
