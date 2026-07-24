<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\View\View;

final class AdminTranslationOverrideController
{
    public function __invoke(): View
    {
        return view('admin.translation-overrides.index');
    }
}
