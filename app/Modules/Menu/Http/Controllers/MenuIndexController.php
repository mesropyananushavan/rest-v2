<?php

declare(strict_types=1);

namespace App\Modules\Menu\Http\Controllers;

use App\Modules\Menu\Application\ListMenuCategories;
use App\Modules\Menu\Application\ListMenuItems;
use Illuminate\View\View;

final class MenuIndexController
{
    public function __invoke(ListMenuCategories $categories, ListMenuItems $items): View
    {
        return view('modules.menu.index', [
            'categories' => $categories(),
            'items' => $items(),
        ]);
    }
}
