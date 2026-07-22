<?php

declare(strict_types=1);

namespace App\Modules\Menu\Http\Controllers;

use Illuminate\View\View;

final class MenuIndexController
{
    public function __invoke(): View
    {
        return view('modules.menu.index');
    }
}
