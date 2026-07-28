<?php

declare(strict_types=1);

use App\Support\Audit\Application\AuditLogReportFilters;
use App\Support\Audit\Application\AuditLogReportRow;

/** @var AuditLogReportFilters $filters */
/** @var AuditLogReportRow $log */
?>

@extends('layouts.admin')

@section('title', __('admin.audit_logs.detail.title'))

@section('content')
    <x-page-header
        :eyebrow="__('admin.audit_logs.eyebrow')"
        :title="__('admin.audit_logs.detail.heading', ['id' => $log->id])"
        :subtitle="__('admin.audit_logs.detail.subtitle')"
    >
        <x-slot:actions>
            <x-button :href="route('admin.audit-logs.index', $filters->queryParameters())" variant="outline-secondary" size="sm">
                {{ __('admin.audit_logs.actions.back_to_list') }}
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-4 xl:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)]">
        <x-card :title="__('admin.audit_logs.detail.summary')">
            <dl class="grid gap-3 text-sm">
                <div>
                    <dt class="font-bold text-smartrest-muted">{{ __('admin.audit_logs.table.created_at') }}</dt>
                    <dd class="mt-1 font-mono text-smartrest-ink">{{ $log->createdAt }}</dd>
                </div>
                <div>
                    <dt class="font-bold text-smartrest-muted">{{ __('admin.audit_logs.table.actor') }}</dt>
                    <dd class="mt-1 text-smartrest-ink">{{ $log->actorLabel() }}</dd>
                </div>
                <div>
                    <dt class="font-bold text-smartrest-muted">{{ __('admin.audit_logs.table.action') }}</dt>
                    <dd class="mt-1"><code class="rounded bg-slate-100 px-2 py-1 text-xs font-bold text-slate-700">{{ $log->action }}</code></dd>
                </div>
                <div>
                    <dt class="font-bold text-smartrest-muted">{{ __('admin.audit_logs.table.target') }}</dt>
                    <dd class="mt-1 text-smartrest-ink">{{ $log->targetType }} #{{ $log->targetId }}</dd>
                </div>
                <div>
                    <dt class="font-bold text-smartrest-muted">{{ __('admin.audit_logs.table.branch') }}</dt>
                    <dd class="mt-1 text-smartrest-ink">{{ $log->branchLabel() }}</dd>
                </div>
                <div>
                    <dt class="font-bold text-smartrest-muted">{{ __('admin.audit_logs.table.correlation_id') }}</dt>
                    <dd class="mt-1 break-all font-mono text-xs text-smartrest-ink">{{ $log->correlationId }}</dd>
                </div>
                <div>
                    <dt class="font-bold text-smartrest-muted">{{ __('admin.audit_logs.table.ip_address') }}</dt>
                    <dd class="mt-1 font-mono text-xs text-smartrest-ink">{{ $log->ipAddress ?? __('admin.audit_logs.values.empty') }}</dd>
                </div>
            </dl>
        </x-card>

        <div class="grid gap-4">
            <x-card :title="__('admin.audit_logs.detail.before_json')">
                <pre class="max-h-[28rem] overflow-auto rounded-sr-brand bg-slate-950 p-4 text-xs leading-5 text-slate-50">{{ $log->beforeJson ?? __('admin.audit_logs.values.empty_json') }}</pre>
            </x-card>

            <x-card :title="__('admin.audit_logs.detail.after_json')">
                <pre class="max-h-[28rem] overflow-auto rounded-sr-brand bg-slate-950 p-4 text-xs leading-5 text-slate-50">{{ $log->afterJson ?? __('admin.audit_logs.values.empty_json') }}</pre>
            </x-card>
        </div>
    </div>
@endsection
