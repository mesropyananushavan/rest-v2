<?php

declare(strict_types=1);

namespace App\Modules\Menu\Http\Controllers\Api;

use App\Modules\Menu\Application\BrowseMenuItems;
use App\Modules\Menu\Http\Requests\MenuItemIndexRequest;
use App\Modules\Menu\Http\Resources\MenuItemResource;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\App;

final class MenuItemIndexController
{
    public function __invoke(
        MenuItemIndexRequest $request,
        BrowseMenuItems $browseItems,
    ): JsonResponse {
        $search = $request->searchQuery();
        $page = $request->page();
        $perPage = $request->perPage();
        $items = $browseItems($request->categoryId(), $search, false, 'active', $perPage, $page);

        return ApiResponse::success($request, MenuItemResource::collection($items->items(), App::getLocale()), [
            'pagination' => ApiResponse::pagination($items),
        ]);
    }
}
