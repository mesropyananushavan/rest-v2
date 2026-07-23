<?php

declare(strict_types=1);

namespace App\Modules\Menu\Http\Controllers\Api;

use App\Modules\Menu\Application\PaginateMenuItems;
use App\Modules\Menu\Application\ResolveMenuCategorySelection;
use App\Modules\Menu\Application\ResolveMenuItemListCategory;
use App\Modules\Menu\Application\SearchMenuItems;
use App\Modules\Menu\Http\Requests\MenuItemIndexRequest;
use App\Modules\Menu\Http\Resources\MenuItemResource;
use App\Modules\Menu\Infrastructure\Models\MenuCategory;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\App;

final class MenuItemIndexController
{
    public function __invoke(
        MenuItemIndexRequest $request,
        PaginateMenuItems $paginateItems,
        SearchMenuItems $searchItems,
        ResolveMenuCategorySelection $categorySelection,
        ResolveMenuItemListCategory $listCategory,
    ): JsonResponse {
        $search = $request->searchQuery();
        $page = $request->page();
        $perPage = $request->perPage();

        if ($search !== null) {
            $items = $searchItems($search, false, 'active', $perPage, $page);

            return ApiResponse::success($request, MenuItemResource::collection($items->items(), App::getLocale()), [
                'pagination' => ApiResponse::pagination($items),
            ]);
        }

        $category = $request->categoryId() === null
            ? $categorySelection(null, 'active')
            : $listCategory($request->categoryId());

        if (! $category instanceof MenuCategory) {
            return ApiResponse::success($request, [], [
                'pagination' => ApiResponse::emptyPagination($page, $perPage),
            ]);
        }

        $items = $paginateItems((int) $category->id, false, 'active', $perPage, $page);

        return ApiResponse::success($request, MenuItemResource::collection($items->items(), App::getLocale()), [
            'pagination' => ApiResponse::pagination($items),
        ]);
    }
}
