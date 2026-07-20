<?php

declare(strict_types=1);

namespace App\Modules\Menu\Http\Controllers;

use App\Modules\Menu\Application\ArchiveMenuItem;
use App\Modules\Menu\Application\CreateMenuItem;
use App\Modules\Menu\Application\ForceDeleteMenuItem;
use App\Modules\Menu\Application\ListMenuCategories;
use App\Modules\Menu\Application\RestoreMenuItem;
use App\Modules\Menu\Application\UpdateMenuItem;
use App\Modules\Menu\Http\Requests\MenuItemRequest;
use App\Modules\Menu\Infrastructure\Models\MenuItem;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Modules\Tenancy\Contracts\TenantSettingsReader;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class MenuItemController
{
    public function create(ListMenuCategories $categories, TenantResolver $tenants, TenantSettingsReader $settings): View
    {
        return view('modules.menu.item-form', [
            'categories' => $categories(),
            'defaultCurrency' => $this->defaultCurrency($tenants, $settings),
            'item' => null,
        ]);
    }

    public function store(MenuItemRequest $request, CreateMenuItem $create): RedirectResponse
    {
        $create(
            $request->categoryId(),
            $request->localizedName(),
            $request->localizedDescription(),
            $request->price(),
            $request->sortOrder(),
            $request->active(),
        );

        return redirect()
            ->route('admin.menu.index')
            ->with('status', __('menu.flash.item_created'));
    }

    public function edit(int $item, ListMenuCategories $categories, BranchContext $branches, TenantResolver $tenants, TenantSettingsReader $settings): View
    {
        return view('modules.menu.item-form', [
            'categories' => $categories(),
            'defaultCurrency' => $this->defaultCurrency($tenants, $settings),
            'item' => $this->findItem($item, $branches),
        ]);
    }

    public function update(int $item, MenuItemRequest $request, UpdateMenuItem $update): RedirectResponse
    {
        $update(
            $item,
            $request->categoryId(),
            $request->localizedName(),
            $request->localizedDescription(),
            $request->price(),
            $request->sortOrder(),
            $request->active(),
        );

        return redirect()
            ->route('admin.menu.index')
            ->with('status', __('menu.flash.item_updated'));
    }

    public function destroy(int $item, ArchiveMenuItem $archive): RedirectResponse
    {
        $archive($item);

        return redirect()
            ->route('admin.menu.index')
            ->with('status', __('menu.flash.item_archived'));
    }

    public function restore(int $item, RestoreMenuItem $restore): RedirectResponse
    {
        $restore($item);

        return redirect()
            ->route('admin.menu.index', ['show_archived' => '1'])
            ->with('status', __('menu.flash.item_restored'));
    }

    public function forceDelete(int $item, ForceDeleteMenuItem $forceDelete): RedirectResponse
    {
        $forceDelete($item);

        return redirect()
            ->route('admin.menu.index', ['show_archived' => '1'])
            ->with('status', __('menu.flash.item_force_deleted'));
    }

    private function findItem(int $item, BranchContext $branches): MenuItem
    {
        $branchId = $branches->id();

        abort_if($branchId === null, 404);

        return MenuItem::query()
            ->where('branch_id', $branchId)
            ->findOrFail($item);
    }

    private function defaultCurrency(TenantResolver $tenants, TenantSettingsReader $settings): string
    {
        $tenantId = $tenants->id();

        if ($tenantId === null) {
            return 'AMD';
        }

        return $settings->settingsFor($tenantId)['currency'] ?? 'AMD';
    }
}
