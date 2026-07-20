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
        $canViewArchive = (bool) data_get($request->user(), 'is_superadmin');
        $showArchived = $canViewArchive && $request->boolean('show_archived');

        return view('modules.menu.index', [
            'categories' => $categories(includeArchived: $showArchived),
            'items' => $items(includeArchived: $showArchived),
            'canViewArchive' => $canViewArchive,
            'showArchived' => $showArchived,
        ]);
    }
}
