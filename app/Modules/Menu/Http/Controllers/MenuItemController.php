<?php

declare(strict_types=1);

namespace App\Modules\Menu\Http\Controllers;

use App\Modules\Menu\Application\ArchiveMenuItem;
use App\Modules\Menu\Application\BrowseMenuItems;
use App\Modules\Menu\Application\CreateMenuItem;
use App\Modules\Menu\Application\ForceDeleteMenuItem;
use App\Modules\Menu\Application\RestoreMenuItem;
use App\Modules\Menu\Application\UpdateMenuItem;
use App\Modules\Menu\Http\MenuIndexContext;
use App\Modules\Menu\Http\Requests\MenuItemRequest;
use App\Modules\Menu\Infrastructure\Models\MenuItem;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Modules\Tenancy\Contracts\TenantSettingsReader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class MenuItemController
{
    public function create(Request $request, TenantResolver $tenants, TenantSettingsReader $settings, BrowseMenuItems $browseItems): View
    {
        $menuContext = MenuIndexContext::fromRequest($request, $browseItems);

        return view('modules.menu.item-form', [
            'defaultCurrency' => $this->defaultCurrency($tenants, $settings),
            'item' => null,
            'menuContext' => $menuContext,
        ]);
    }

    public function store(MenuItemRequest $request, CreateMenuItem $create, BrowseMenuItems $browseItems): RedirectResponse
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
            ->to(MenuIndexContext::fromRequest($request, $browseItems)->url())
            ->with('status', __('menu.flash.item_created'));
    }

    public function edit(int $item, Request $request, BranchContext $branches, TenantResolver $tenants, TenantSettingsReader $settings, BrowseMenuItems $browseItems): View
    {
        return view('modules.menu.item-form', [
            'defaultCurrency' => $this->defaultCurrency($tenants, $settings),
            'item' => $this->findItem($item, $branches),
            'menuContext' => MenuIndexContext::fromRequest($request, $browseItems),
        ]);
    }

    public function update(int $item, MenuItemRequest $request, UpdateMenuItem $update, BrowseMenuItems $browseItems): RedirectResponse
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
            ->to(MenuIndexContext::fromRequest($request, $browseItems)->url())
            ->with('status', __('menu.flash.item_updated'));
    }

    public function destroy(int $item, Request $request, ArchiveMenuItem $archive, BrowseMenuItems $browseItems): RedirectResponse
    {
        $archive($item);

        return redirect()
            ->to(MenuIndexContext::fromRequest($request, $browseItems)->url())
            ->with('status', __('menu.flash.item_archived'));
    }

    public function restore(int $item, Request $request, RestoreMenuItem $restore, BrowseMenuItems $browseItems): RedirectResponse
    {
        $restore($item);

        return redirect()
            ->to(MenuIndexContext::fromRequest($request, $browseItems, 'archived')->url())
            ->with('status', __('menu.flash.item_restored'));
    }

    public function forceDelete(int $item, Request $request, ForceDeleteMenuItem $forceDelete, BrowseMenuItems $browseItems): RedirectResponse
    {
        $forceDelete($item);

        return redirect()
            ->to(MenuIndexContext::fromRequest($request, $browseItems, 'archived')->url())
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
