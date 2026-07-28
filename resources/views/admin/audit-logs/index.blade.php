<?php

declare(strict_types=1);

use App\Support\Audit\Application\AuditLogReportFilters;
use App\Support\Audit\Application\AuditLogReportRow;
use Illuminate\Pagination\LengthAwarePaginator;

/** @var list<array{id: int, name: string}> $branchOptions */
/** @var AuditLogReportFilters $filters */
/** @var LengthAwarePaginator<int, AuditLogReportRow> $logs */
/** @var int $maxWindowDays */
?>

@extends('layouts.admin')

@section('title', __('admin.audit_logs.title'))

@section('content')
    <x-page-header
        :eyebrow="__('admin.audit_logs.eyebrow')"
        :title="__('admin.audit_logs.heading')"
        :subtitle="__('admin.audit_logs.subtitle', ['days' => $maxWindowDays])"
    />

    <x-card class="mb-4">
        @if ($errors->any())
            <div class="mb-4 rounded-sr-brand border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="get" action="{{ route('admin.audit-logs.index') }}" class="grid gap-3 md:grid-cols-2 xl:grid-cols-6 xl:items-end">
            <div>
                <label for="audit_date_from" class="mb-1.5 block text-sm font-semibold text-slate-700">{{ __('admin.audit_logs.filters.date_from') }}</label>
                <input id="audit_date_from" type="date" name="date_from" value="{{ $filters->dateFrom }}" class="block w-full rounded-sr-control border border-slate-200 bg-white px-3 py-2 text-sm text-smartrest-text shadow-sm outline-none transition focus:border-smartrest-success focus:ring-4 focus:ring-smartrest-success/10" required>
            </div>

            <div>
                <label for="audit_date_to" class="mb-1.5 block text-sm font-semibold text-slate-700">{{ __('admin.audit_logs.filters.date_to') }}</label>
                <input id="audit_date_to" type="date" name="date_to" value="{{ $filters->dateTo }}" class="block w-full rounded-sr-control border border-slate-200 bg-white px-3 py-2 text-sm text-smartrest-text shadow-sm outline-none transition focus:border-smartrest-success focus:ring-4 focus:ring-smartrest-success/10" required>
            </div>

            <div>
                <label for="audit_actor_id" class="mb-1.5 block text-sm font-semibold text-slate-700">{{ __('admin.audit_logs.filters.actor_id') }}</label>
                <input id="audit_actor_id" type="number" min="1" name="actor_id" value="{{ $filters->actorId }}" class="block w-full rounded-sr-control border border-slate-200 bg-white px-3 py-2 text-sm text-smartrest-text shadow-sm outline-none transition focus:border-smartrest-success focus:ring-4 focus:ring-smartrest-success/10" placeholder="{{ __('admin.audit_logs.filters.actor_placeholder') }}">
            </div>

            <div>
                <label for="audit_action" class="mb-1.5 block text-sm font-semibold text-slate-700">{{ __('admin.audit_logs.filters.action') }}</label>
                <input id="audit_action" type="text" name="action" value="{{ $filters->action }}" class="block w-full rounded-sr-control border border-slate-200 bg-white px-3 py-2 text-sm text-smartrest-text shadow-sm outline-none transition focus:border-smartrest-success focus:ring-4 focus:ring-smartrest-success/10" placeholder="{{ __('admin.audit_logs.filters.action_placeholder') }}">
            </div>

            <div>
                <label for="audit_target_type" class="mb-1.5 block text-sm font-semibold text-slate-700">{{ __('admin.audit_logs.filters.target_type') }}</label>
                <input id="audit_target_type" type="text" name="target_type" value="{{ $filters->targetType }}" class="block w-full rounded-sr-control border border-slate-200 bg-white px-3 py-2 text-sm text-smartrest-text shadow-sm outline-none transition focus:border-smartrest-success focus:ring-4 focus:ring-smartrest-success/10" placeholder="{{ __('admin.audit_logs.filters.target_type_placeholder') }}">
            </div>

            <div>
                <label for="audit_branch_id" class="mb-1.5 block text-sm font-semibold text-slate-700">{{ __('admin.audit_logs.filters.branch') }}</label>
                <select id="audit_branch_id" name="branch_id" class="block w-full rounded-sr-control border border-slate-200 bg-white px-3 py-2 text-sm text-smartrest-text shadow-sm outline-none transition focus:border-smartrest-success focus:ring-4 focus:ring-smartrest-success/10">
                    <option value="">{{ __('admin.audit_logs.filters.all_assigned_branches') }}</option>
                    @foreach ($branchOptions as $branch)
                        <option value="{{ $branch['id'] }}" @selected($filters->branchId === $branch['id'])>
                            {{ $branch['name'] }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="flex gap-2 xl:col-span-6">
                <x-button type="submit">
                    {{ __('admin.audit_logs.actions.apply_filters') }}
                </x-button>
                <x-button :href="route('admin.audit-logs.index')" variant="outline-secondary">
                    {{ __('admin.audit_logs.actions.clear_filters') }}
                </x-button>
            </div>
        </form>
    </x-card>

    <x-card :title="__('admin.audit_logs.results.heading')" :count="$logs->total()" body-class="p-0">
        @if ($logs->isEmpty())
            <div class="px-5 py-10 text-center">
                <h2 class="text-lg font-bold text-smartrest-ink">{{ __('admin.audit_logs.empty.title') }}</h2>
                <p class="mt-1 text-sm text-smartrest-muted">{{ __('admin.audit_logs.empty.body') }}</p>
            </div>
        @else
            <x-table>
                <thead>
                    <tr>
                        <th>{{ __('admin.audit_logs.table.created_at') }}</th>
                        <th>{{ __('admin.audit_logs.table.actor') }}</th>
                        <th>{{ __('admin.audit_logs.table.action') }}</th>
                        <th>{{ __('admin.audit_logs.table.target') }}</th>
                        <th>{{ __('admin.audit_logs.table.branch') }}</th>
                        <th class="text-right">{{ __('admin.audit_logs.table.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($logs as $log)
                        <tr>
                            <td class="whitespace-nowrap font-mono text-xs text-slate-700">{{ $log->createdAt }}</td>
                            <td>{{ $log->actorLabel() }}</td>
                            <td><code class="rounded bg-slate-100 px-2 py-1 text-xs font-bold text-slate-700">{{ $log->action }}</code></td>
                            <td>
                                <span class="font-semibold text-smartrest-ink">{{ $log->targetType }}</span>
                                <span class="text-smartrest-muted">#{{ $log->targetId }}</span>
                            </td>
                            <td>{{ $log->branchLabel() }}</td>
                            <td class="text-right">
                                <x-button :href="route('admin.audit-logs.show', array_merge(['auditLog' => $log->id], $filters->queryParameters()))" variant="outline-secondary" size="sm">
                                    {{ __('admin.audit_logs.actions.view_details') }}
                                </x-button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-table>

            <div class="px-4 py-3">
                {{ $logs->withQueryString()->links() }}
            </div>
        @endif
    </x-card>
@endsection
