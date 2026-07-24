<?php

declare(strict_types=1);

namespace App\Modules\Menu\Http\Controllers;

use App\Modules\Menu\Application\ArchiveMenuCategory;
use App\Modules\Menu\Application\BrowseMenuItems;
use App\Modules\Menu\Application\CreateMenuCategory;
use App\Modules\Menu\Application\ForceDeleteMenuCategory;
use App\Modules\Menu\Application\RestoreMenuCategory;
use App\Modules\Menu\Application\SearchMenuCategoryOptions;
use App\Modules\Menu\Application\UpdateMenuCategory;
use App\Modules\Menu\Http\MenuIndexContext;
use App\Modules\Menu\Http\Requests\MenuCategoryRequest;
use App\Modules\Menu\Infrastructure\Models\MenuCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class MenuCategoryController
{
    public function create(Request $request, SearchMenuCategoryOptions $categoryOptions, BrowseMenuItems $browseItems): View
    {
        $menuContext = MenuIndexContext::fromRequest($request, $browseItems);

        return view('modules.menu.category-form', [
            'category' => null,
            'menuContext' => $menuContext,
            'parentOptionsEndpoint' => route('admin.menu.category-options.parents'),
            'parentInitialOptions' => $this->initialParentOptions($categoryOptions),
            'selectedParentValue' => $this->selectedParentValue(),
            'selectedParentOption' => $this->selectedParentOption($categoryOptions),
        ]);
    }

    public function store(MenuCategoryRequest $request, CreateMenuCategory $create, BrowseMenuItems $browseItems): RedirectResponse
    {
        $create($request->localizedName(), $request->sortOrder(), $request->active(), $request->parentId());

        return redirect()
            ->to(MenuIndexContext::fromRequest($request, $browseItems)->url())
            ->with('status', __('menu.flash.category_created'));
    }

    public function edit(int $category, Request $request, SearchMenuCategoryOptions $categoryOptions, BrowseMenuItems $browseItems): View
    {
        $categoryModel = MenuCategory::query()->findOrFail($category);
        $excludedCategoryId = (int) $categoryModel->id;
        $menuContext = MenuIndexContext::fromRequest($request, $browseItems);

        return view('modules.menu.category-form', [
            'category' => $categoryModel,
            'menuContext' => $menuContext,
            'parentOptionsEndpoint' => route('admin.menu.category-options.parents', ['exclude_id' => $excludedCategoryId]),
            'parentInitialOptions' => $this->initialParentOptions($categoryOptions, $excludedCategoryId),
            'selectedParentValue' => $this->selectedParentValue($categoryModel),
            'selectedParentOption' => $this->selectedParentOption($categoryOptions, $categoryModel),
        ]);
    }

    public function update(int $category, MenuCategoryRequest $request, UpdateMenuCategory $update, BrowseMenuItems $browseItems): RedirectResponse
    {
        $categoryModel = MenuCategory::query()->findOrFail($category);

        $update($category, $request->localizedName(), $request->sortOrder(), $request->active(), $request->parentIdOr($categoryModel->parent_id === null ? null : (int) $categoryModel->parent_id));

        return redirect()
            ->to(MenuIndexContext::fromRequest($request, $browseItems)->url())
            ->with('status', __('menu.flash.category_updated'));
    }

    public function destroy(int $category, Request $request, ArchiveMenuCategory $archive, BrowseMenuItems $browseItems): RedirectResponse
    {
        $archive($category);

        return redirect()
            ->to(MenuIndexContext::fromRequest($request, $browseItems)->url())
            ->with('status', __('menu.flash.category_archived'));
    }

    public function restore(int $category, Request $request, RestoreMenuCategory $restore, BrowseMenuItems $browseItems): RedirectResponse
    {
        $restore($category);

        return redirect()
            ->to(MenuIndexContext::fromRequest($request, $browseItems, 'archived')->url())
            ->with('status', __('menu.flash.category_restored'));
    }

    public function forceDelete(int $category, Request $request, ForceDeleteMenuCategory $forceDelete, BrowseMenuItems $browseItems): RedirectResponse
    {
        $forceDelete($category);

        return redirect()
            ->to(MenuIndexContext::fromRequest($request, $browseItems, 'archived')->url())
            ->with('status', __('menu.flash.category_force_deleted'));
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    private function initialParentOptions(SearchMenuCategoryOptions $categoryOptions, ?int $excludedCategoryId = null): array
    {
        return $categoryOptions(SearchMenuCategoryOptions::MODE_ROOTS, excludeId: $excludedCategoryId)['options'];
    }

    /**
     * @return array{id: int, label: string}|null
     */
    private function selectedParentOption(SearchMenuCategoryOptions $categoryOptions, ?MenuCategory $category = null): ?array
    {
        $parentId = $this->selectedParentValue($category);

        if ($parentId <= 0) {
            return [
                'id' => 0,
                'label' => __('menu.categories.root_parent_option'),
            ];
        }

        $excludedCategoryId = $category instanceof MenuCategory ? (int) $category->id : null;

        return $categoryOptions->selectedOption(SearchMenuCategoryOptions::MODE_ROOTS, $parentId, $excludedCategoryId);
    }

    private function selectedParentValue(?MenuCategory $category = null): int
    {
        $oldParentId = request()->old('parent_id');

        if (is_numeric($oldParentId)) {
            return (int) $oldParentId;
        }

        if (! $category instanceof MenuCategory) {
            $queryParentId = request()->query('parent_id');

            return is_numeric($queryParentId) ? (int) $queryParentId : 0;
        }

        return $category->parent_id === null ? 0 : (int) $category->parent_id;
    }
}
