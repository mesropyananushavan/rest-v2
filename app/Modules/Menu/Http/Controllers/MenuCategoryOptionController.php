<?php

declare(strict_types=1);

namespace App\Modules\Menu\Http\Controllers;

use App\Modules\Menu\Application\SearchMenuCategoryOptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MenuCategoryOptionController
{
    public function parents(Request $request, SearchMenuCategoryOptions $search): JsonResponse
    {
        return response()->json($search(
            SearchMenuCategoryOptions::MODE_ROOTS,
            $this->search($request),
            $this->perPage($request),
            $this->page($request),
            $this->excludeId($request),
        ));
    }

    public function itemCategories(Request $request, SearchMenuCategoryOptions $search): JsonResponse
    {
        return response()->json($search(
            SearchMenuCategoryOptions::MODE_SUBCATEGORIES,
            $this->search($request),
            $this->perPage($request),
            $this->page($request),
        ));
    }

    private function search(Request $request): ?string
    {
        $search = $request->query('q');

        return is_string($search) ? $search : null;
    }

    private function page(Request $request): int
    {
        return max(1, $request->integer('page', 1));
    }

    private function perPage(Request $request): int
    {
        return max(1, $request->integer('per_page', 10));
    }

    private function excludeId(Request $request): ?int
    {
        $excludeId = $request->query('exclude_id');

        if (! is_numeric($excludeId) || (int) $excludeId <= 0) {
            return null;
        }

        return (int) $excludeId;
    }
}
