<?php

declare(strict_types=1);

namespace App\Modules\Payments\Http\Controllers;

use App\Modules\Payments\Application\ActivateCashbox;
use App\Modules\Payments\Application\CreateCashbox;
use App\Modules\Payments\Application\DeactivateCashbox;
use App\Modules\Payments\Application\FindCashbox;
use App\Modules\Payments\Application\PaginateCashboxes;
use App\Modules\Payments\Application\SelectDefaultCashbox;
use App\Modules\Payments\Application\UpdateCashbox;
use App\Modules\Payments\Http\Requests\CashboxRequest;
use App\Modules\Payments\Infrastructure\Models\Cashbox;
use App\Modules\Tenancy\Contracts\BranchContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class CashboxController
{
    public function index(Request $request, BranchContext $branches, PaginateCashboxes $cashboxes): View
    {
        $includeInactive = ! $request->boolean('active_only');
        $paginator = $cashboxes($includeInactive, (int) $request->integer('per_page', 25), (int) $request->integer('page', 1));
        $branchId = $branches->id();
        $activeCashboxCount = $branchId === null
            ? 0
            : Cashbox::query()
                ->where('branch_id', $branchId)
                ->where('is_active', true)
                ->count();

        return view('modules.payments.cashboxes.index', [
            'activeCashboxCount' => $activeCashboxCount,
            'cashboxes' => $paginator,
            'includeInactive' => $includeInactive,
        ]);
    }

    public function create(): View
    {
        return view('modules.payments.cashboxes.form', [
            'cashbox' => null,
        ]);
    }

    public function store(CashboxRequest $request, CreateCashbox $create): RedirectResponse
    {
        $create($request->cashboxName());

        return redirect()
            ->route('admin.payments.cashboxes.index')
            ->with('status', __('payments.cashboxes.flash.created'));
    }

    public function edit(int $cashbox, FindCashbox $findCashbox): View
    {
        return view('modules.payments.cashboxes.form', [
            'cashbox' => $findCashbox($cashbox),
        ]);
    }

    public function update(int $cashbox, CashboxRequest $request, UpdateCashbox $update): RedirectResponse
    {
        $update($cashbox, $request->cashboxName());

        return redirect()
            ->route('admin.payments.cashboxes.index')
            ->with('status', __('payments.cashboxes.flash.updated'));
    }

    public function activate(int $cashbox, ActivateCashbox $activate): RedirectResponse
    {
        $activate($cashbox);

        return redirect()
            ->route('admin.payments.cashboxes.index')
            ->with('status', __('payments.cashboxes.flash.activated'));
    }

    public function deactivate(int $cashbox, Request $request, DeactivateCashbox $deactivate): RedirectResponse
    {
        $replacementDefaultId = $request->integer('replacement_default_id');

        $deactivate($cashbox, $replacementDefaultId > 0 ? $replacementDefaultId : null);

        return redirect()
            ->route('admin.payments.cashboxes.index')
            ->with('status', __('payments.cashboxes.flash.deactivated'));
    }

    public function selectDefault(int $cashbox, SelectDefaultCashbox $selectDefault): RedirectResponse
    {
        $selectDefault($cashbox);

        return redirect()
            ->route('admin.payments.cashboxes.index')
            ->with('status', __('payments.cashboxes.flash.default_selected'));
    }
}
