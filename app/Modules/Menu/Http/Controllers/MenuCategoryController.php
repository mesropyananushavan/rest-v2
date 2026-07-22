<?php

declare(strict_types=1);

namespace App\Modules\Menu\Http\Controllers;

use App\Modules\Menu\Application\ArchiveMenuCategory;
use App\Modules\Menu\Application\CreateMenuCategory;
use App\Modules\Menu\Application\ForceDeleteMenuCategory;
use App\Modules\Menu\Application\RestoreMenuCategory;
use App\Modules\Menu\Application\UpdateMenuCategory;
use App\Modules\Menu\Http\Requests\MenuCategoryRequest;
use App\Modules\Menu\Infrastructure\Models\MenuCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class MenuCategoryController
{
    public function create(): View
    {
        return view('modules.menu.category-form', [
            'category' => null,
        ]);
    }

    public function store(MenuCategoryRequest $request, CreateMenuCategory $create): RedirectResponse
    {
        $create($request->localizedName(), $request->sortOrder(), $request->active());

        return redirect()
            ->route('admin.menu.index')
            ->with('status', __('menu.flash.category_created'));
    }

    public function edit(int $category): View
    {
        return view('modules.menu.category-form', [
            'category' => MenuCategory::query()->findOrFail($category),
        ]);
    }

    public function update(int $category, MenuCategoryRequest $request, UpdateMenuCategory $update): RedirectResponse
    {
        $update($category, $request->localizedName(), $request->sortOrder(), $request->active());

        return redirect()
            ->route('admin.menu.index')
            ->with('status', __('menu.flash.category_updated'));
    }

    public function destroy(int $category, ArchiveMenuCategory $archive): RedirectResponse
    {
        $archive($category);

        return redirect()
            ->route('admin.menu.index')
            ->with('status', __('menu.flash.category_archived'));
    }

    public function restore(int $category, RestoreMenuCategory $restore): RedirectResponse
    {
        $restore($category);

        return redirect()
            ->route('admin.menu.index', ['archive_mode' => 'archived'])
            ->with('status', __('menu.flash.category_restored'));
    }

    public function forceDelete(int $category, ForceDeleteMenuCategory $forceDelete): RedirectResponse
    {
        $forceDelete($category);

        return redirect()
            ->route('admin.menu.index', ['archive_mode' => 'archived'])
            ->with('status', __('menu.flash.category_force_deleted'));
    }
}
