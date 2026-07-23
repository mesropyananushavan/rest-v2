<?php

declare(strict_types=1);

namespace App\Modules\Tables\Http\Controllers;

use App\Modules\Tables\Application\ArchiveTable;
use App\Modules\Tables\Application\CreateTable;
use App\Modules\Tables\Application\FindHall;
use App\Modules\Tables\Application\FindTable;
use App\Modules\Tables\Application\ForceDeleteTable;
use App\Modules\Tables\Application\PaginateTables;
use App\Modules\Tables\Application\RestoreTable;
use App\Modules\Tables\Application\UpdateTable;
use App\Modules\Tables\Http\Requests\TableRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class TableController
{
    public function index(int $hall, Request $request, FindHall $findHall, PaginateTables $tables): View
    {
        $canViewArchive = (bool) data_get($request->user(), 'is_superadmin');
        $archiveMode = $canViewArchive ? $this->archiveMode($request) : 'active';
        $includeInactive = $request->boolean('show_inactive');

        return view('modules.tables.tables.index', [
            'archiveMode' => $archiveMode,
            'canViewArchive' => $canViewArchive,
            'hall' => $findHall($hall),
            'includeInactive' => $includeInactive,
            'tables' => $tables($hall, $includeInactive, $archiveMode, (int) $request->integer('per_page', 25), (int) $request->integer('page', 1)),
        ]);
    }

    public function create(int $hall, FindHall $findHall): View
    {
        return view('modules.tables.tables.form', [
            'hall' => $findHall($hall),
            'table' => null,
        ]);
    }

    public function store(int $hall, TableRequest $request, CreateTable $create): RedirectResponse
    {
        $create(
            $hall,
            $request->localizedName(),
            $request->type(),
            $request->shape(),
            $request->hdmDepartment(),
            $request->isDelivery(),
            $request->sortOrder(),
            $request->active(),
        );

        return redirect()
            ->route('admin.tables.tables.index', ['hall' => $hall])
            ->with('status', __('tables.tables.flash.created'));
    }

    public function edit(int $hall, int $table, FindHall $findHall, FindTable $findTable): View
    {
        return view('modules.tables.tables.form', [
            'hall' => $findHall($hall),
            'table' => $findTable($hall, $table),
        ]);
    }

    public function update(int $hall, int $table, TableRequest $request, UpdateTable $update): RedirectResponse
    {
        $update(
            $hall,
            $table,
            $request->localizedName(),
            $request->type(),
            $request->shape(),
            $request->hdmDepartment(),
            $request->isDelivery(),
            $request->sortOrder(),
            $request->active(),
        );

        return redirect()
            ->route('admin.tables.tables.index', ['hall' => $hall])
            ->with('status', __('tables.tables.flash.updated'));
    }

    public function destroy(int $hall, int $table, ArchiveTable $archive): RedirectResponse
    {
        $archive($hall, $table);

        return redirect()
            ->route('admin.tables.tables.index', ['hall' => $hall])
            ->with('status', __('tables.tables.flash.archived'));
    }

    public function restore(int $hall, int $table, RestoreTable $restore): RedirectResponse
    {
        $restore($hall, $table);

        return redirect()
            ->route('admin.tables.tables.index', ['hall' => $hall, 'archive_mode' => 'archived'])
            ->with('status', __('tables.tables.flash.restored'));
    }

    public function forceDelete(int $hall, int $table, ForceDeleteTable $forceDelete): RedirectResponse
    {
        $forceDelete($hall, $table);

        return redirect()
            ->route('admin.tables.tables.index', ['hall' => $hall, 'archive_mode' => 'archived'])
            ->with('status', __('tables.tables.flash.force_deleted'));
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
