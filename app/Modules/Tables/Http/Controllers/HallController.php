<?php

declare(strict_types=1);

namespace App\Modules\Tables\Http\Controllers;

use App\Modules\Tables\Application\ArchiveHall;
use App\Modules\Tables\Application\CreateHall;
use App\Modules\Tables\Application\FindHall;
use App\Modules\Tables\Application\ForceDeleteHall;
use App\Modules\Tables\Application\PaginateHalls;
use App\Modules\Tables\Application\RestoreHall;
use App\Modules\Tables\Application\UpdateHall;
use App\Modules\Tables\Http\Requests\HallRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class HallController
{
    public function index(Request $request, PaginateHalls $halls): View
    {
        $canViewArchive = (bool) data_get($request->user(), 'is_superadmin');
        $archiveMode = $canViewArchive ? $this->archiveMode($request) : 'active';
        $includeInactive = $request->boolean('show_inactive');

        return view('modules.tables.halls.index', [
            'archiveMode' => $archiveMode,
            'canViewArchive' => $canViewArchive,
            'halls' => $halls($includeInactive, $archiveMode, (int) $request->integer('per_page', 25), (int) $request->integer('page', 1)),
            'includeInactive' => $includeInactive,
        ]);
    }

    public function create(): View
    {
        return view('modules.tables.halls.form', [
            'hall' => null,
        ]);
    }

    public function store(HallRequest $request, CreateHall $create): RedirectResponse
    {
        $create($request->localizedName(), $request->color(), $request->sortOrder(), $request->active());

        return redirect()
            ->route('admin.tables.halls.index')
            ->with('status', __('tables.halls.flash.created'));
    }

    public function edit(int $hall, FindHall $findHall): View
    {
        return view('modules.tables.halls.form', [
            'hall' => $findHall($hall),
        ]);
    }

    public function update(int $hall, HallRequest $request, UpdateHall $update): RedirectResponse
    {
        $update($hall, $request->localizedName(), $request->color(), $request->sortOrder(), $request->active());

        return redirect()
            ->route('admin.tables.halls.index')
            ->with('status', __('tables.halls.flash.updated'));
    }

    public function destroy(int $hall, ArchiveHall $archive): RedirectResponse
    {
        $archive($hall);

        return redirect()
            ->route('admin.tables.halls.index')
            ->with('status', __('tables.halls.flash.archived'));
    }

    public function restore(int $hall, RestoreHall $restore): RedirectResponse
    {
        $restore($hall);

        return redirect()
            ->route('admin.tables.halls.index', ['archive_mode' => 'archived'])
            ->with('status', __('tables.halls.flash.restored'));
    }

    public function forceDelete(int $hall, ForceDeleteHall $forceDelete): RedirectResponse
    {
        $forceDelete($hall);

        return redirect()
            ->route('admin.tables.halls.index', ['archive_mode' => 'archived'])
            ->with('status', __('tables.halls.flash.force_deleted'));
    }

    /**
     * @return 'active'|'archived'|'all'
     */
    private function archiveMode(Request $request): string
    {
        $archiveMode = $request->query('archive_mode');

        if (! is_string($archiveMode) || ! in_array($archiveMode, ['active', 'archived', 'all'], true)) {
            return 'active';
        }

        return $archiveMode;
    }
}
