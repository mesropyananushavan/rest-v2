<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Menu\Infrastructure\Models\MenuCategory;
use App\Modules\Menu\Infrastructure\Models\MenuItem;
use Illuminate\View\View;

final class AdminDashboardController
{
    public function __invoke(): View
    {
        return view('admin-dashboard', [
            'categoryCount' => MenuCategory::query()->count(),
            'itemCount' => MenuItem::query()->count(),
        ]);
    }
}
