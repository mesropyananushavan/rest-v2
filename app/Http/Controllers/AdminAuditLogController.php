<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Identity\Contracts\UserDirectory;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantDirectory;
use App\Modules\Tenancy\Infrastructure\Models\Branch;
use App\Support\Audit\Application\AuditLogReportFilters;
use App\Support\Audit\Application\BrowseAuditLogs;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator as ValidationValidator;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class AdminAuditLogController
{
    public function index(
        Request $request,
        BrowseAuditLogs $auditLogs,
        BranchContext $branches,
        TenantDirectory $tenantDirectory,
        UserDirectory $users,
    ): View {
        $timezone = $this->timezone($branches->id());
        $assignedBranchIds = $this->assignedBranchIds($request, $users);
        $filters = $this->filters($request, $timezone, $assignedBranchIds);

        return view('admin.audit-logs.index', [
            'branchOptions' => $tenantDirectory->branchSummariesForIds($assignedBranchIds),
            'filters' => $filters,
            'logs' => $auditLogs->paginate($filters, $timezone, (int) $request->integer('page', 1)),
            'maxWindowDays' => BrowseAuditLogs::MAX_WINDOW_DAYS,
        ]);
    }

    public function show(
        int $auditLog,
        Request $request,
        BrowseAuditLogs $auditLogs,
        BranchContext $branches,
        UserDirectory $users,
    ): View {
        $timezone = $this->timezone($branches->id());
        $filters = $this->filters($request, $timezone, $this->assignedBranchIds($request, $users));
        $row = $auditLogs->find($auditLog, $filters, $timezone);

        if ($row === null) {
            throw new NotFoundHttpException;
        }

        return view('admin.audit-logs.show', [
            'filters' => $filters,
            'log' => $row,
        ]);
    }

    /**
     * @param  list<int>  $assignedBranchIds
     */
    private function filters(Request $request, string $timezone, array $assignedBranchIds): AuditLogReportFilters
    {
        $defaults = $this->defaultDateInputs($timezone);
        $input = [
            'date_from' => $this->queryString($request, 'date_from', $defaults['date_from']),
            'date_to' => $this->queryString($request, 'date_to', $defaults['date_to']),
            'actor_id' => $this->queryString($request, 'actor_id'),
            'action' => $this->queryString($request, 'action'),
            'target_type' => $this->queryString($request, 'target_type'),
            'branch_id' => $this->queryString($request, 'branch_id'),
        ];

        $validator = Validator::make($input, [
            'date_from' => ['required', 'date_format:Y-m-d'],
            'date_to' => ['required', 'date_format:Y-m-d'],
            'actor_id' => ['nullable', 'integer', 'min:1'],
            'action' => ['nullable', 'string', 'max:128', 'regex:/^[A-Za-z0-9_.-]+$/'],
            'target_type' => ['nullable', 'string', 'max:128', 'regex:/^[A-Za-z0-9_.-]+$/'],
            'branch_id' => ['nullable', 'integer', 'min:1'],
        ], $this->validationMessages());

        $validator->after(function (ValidationValidator $validator) use ($assignedBranchIds, $input, $timezone): void {
            if ($validator->errors()->has('date_from') || $validator->errors()->has('date_to')) {
                return;
            }

            $from = CarbonImmutable::parse((string) $input['date_from'], $timezone)->startOfDay();
            $to = CarbonImmutable::parse((string) $input['date_to'], $timezone)->startOfDay();

            if ($from->greaterThan($to)) {
                $validator->errors()->add('date_to', __('admin.audit_logs.errors.date_order'));

                return;
            }

            if ($from->diffInDays($to) >= BrowseAuditLogs::MAX_WINDOW_DAYS) {
                $validator->errors()->add('date_to', __('admin.audit_logs.errors.window_too_large', [
                    'days' => BrowseAuditLogs::MAX_WINDOW_DAYS,
                ]));
            }

            if ($input['branch_id'] === null || $input['branch_id'] === '') {
                return;
            }

            $branchId = (int) $input['branch_id'];

            if (! in_array($branchId, $assignedBranchIds, true)) {
                $validator->errors()->add('branch_id', __('admin.audit_logs.errors.branch_not_allowed'));
            }
        });

        try {
            /** @var array{date_from: string, date_to: string, actor_id?: string|null, action?: string|null, target_type?: string|null, branch_id?: string|null} $validated */
            $validated = $validator->validate();
        } catch (ValidationException $exception) {
            throw $exception;
        }

        $fromLocal = CarbonImmutable::parse($validated['date_from'], $timezone)->startOfDay();
        $toLocal = CarbonImmutable::parse($validated['date_to'], $timezone)->endOfDay();

        return new AuditLogReportFilters(
            dateFrom: $validated['date_from'],
            dateTo: $validated['date_to'],
            fromUtc: $fromLocal->setTimezone('UTC'),
            toUtc: $toLocal->setTimezone('UTC'),
            visibleBranchIds: $assignedBranchIds,
            actorId: isset($validated['actor_id']) && $validated['actor_id'] !== '' ? (int) $validated['actor_id'] : null,
            action: isset($validated['action']) && $validated['action'] !== '' ? $validated['action'] : null,
            targetType: isset($validated['target_type']) && $validated['target_type'] !== '' ? $validated['target_type'] : null,
            branchId: isset($validated['branch_id']) && $validated['branch_id'] !== '' ? (int) $validated['branch_id'] : null,
        );
    }

    /**
     * @return array{date_from: string, date_to: string}
     */
    private function defaultDateInputs(string $timezone): array
    {
        $today = CarbonImmutable::now($timezone)->startOfDay();

        return [
            'date_from' => $today->subDays(BrowseAuditLogs::DEFAULT_WINDOW_DAYS - 1)->format('Y-m-d'),
            'date_to' => $today->format('Y-m-d'),
        ];
    }

    /**
     * @return list<int>
     */
    private function assignedBranchIds(Request $request, UserDirectory $users): array
    {
        $userId = $request->user()?->getAuthIdentifier();

        return is_numeric($userId) ? $users->assignedBranchIds((int) $userId) : [];
    }

    private function timezone(?int $branchId): string
    {
        if ($branchId === null) {
            return $this->fallbackTimezone();
        }

        $timezone = Branch::query()
            ->whereKey($branchId)
            ->value('timezone');

        return is_string($timezone) && $timezone !== '' ? $timezone : $this->fallbackTimezone();
    }

    private function queryString(Request $request, string $key, ?string $default = null): ?string
    {
        $value = $request->query($key, $default);

        if ($value === null) {
            return null;
        }

        return is_scalar($value) ? trim((string) $value) : null;
    }

    private function fallbackTimezone(): string
    {
        $timezone = config('app.timezone', 'UTC');

        return is_string($timezone) && $timezone !== '' ? $timezone : 'UTC';
    }

    /**
     * @return array<string, string>
     */
    private function validationMessages(): array
    {
        return [
            'date_from.required' => __('admin.audit_logs.errors.date_required'),
            'date_from.date_format' => __('admin.audit_logs.errors.date_invalid'),
            'date_to.required' => __('admin.audit_logs.errors.date_required'),
            'date_to.date_format' => __('admin.audit_logs.errors.date_invalid'),
            'actor_id.integer' => __('admin.audit_logs.errors.actor_invalid'),
            'actor_id.min' => __('admin.audit_logs.errors.actor_invalid'),
            'action.max' => __('admin.audit_logs.errors.action_invalid'),
            'action.regex' => __('admin.audit_logs.errors.action_invalid'),
            'target_type.max' => __('admin.audit_logs.errors.target_type_invalid'),
            'target_type.regex' => __('admin.audit_logs.errors.target_type_invalid'),
            'branch_id.integer' => __('admin.audit_logs.errors.branch_invalid'),
            'branch_id.min' => __('admin.audit_logs.errors.branch_invalid'),
        ];
    }
}
