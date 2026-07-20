<?php

declare(strict_types=1);

namespace App\Modules\Menu\Http\Controllers;

use App\Modules\Menu\Application\ListMenuCategories;
use App\Modules\Menu\Application\ListMenuItems;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class MenuIndexController
{
    public function __invoke(Request $request, ListMenuCategories $categories, ListMenuItems $items): View
    {
        $showArchived = $request->boolean('show_archived');

        return view('modules.menu.index', [
            'categories' => $categories(includeArchived: $showArchived),
            'items' => $items(includeArchived: $showArchived),
            'showArchived' => $showArchived,
        ]);
    }
}
