<?php

declare(strict_types=1);

use App\Modules\Tables\Infrastructure\Models\Hall;
use App\Modules\Tables\Infrastructure\Models\Table;
use Illuminate\Pagination\LengthAwarePaginator;

/** @var Hall $hall */
/** @var LengthAwarePaginator<int, Table> $tables */
/** @var 'active'|'archived'|'all' $archiveMode */
/** @var bool $canViewArchive */
/** @var bool $includeInactive */
?>

@extends('layouts.admin')

@section('title', __('tables.tables.index.title'))

@section('content')
    <x-page-header
        :eyebrow="__('tables.tables.index.eyebrow')"
        :title="__('tables.tables.index.heading', ['hall' => $hall->translatedName()->forLocale(app()->getLocale(), 'en')])"
        :subtitle="__('tables.tables.index.subtitle')"
    >
        <x-slot:actions>
            <x-button :href="route('admin.tables.halls.index')" variant="outline-secondary" size="sm">
                {{ __('tables.tables.actions.back_to_halls') }}
            </x-button>
            <x-button :href="route('admin.tables.tables.create', ['hall' => (int) $hall->id])" size="sm">
                {{ __('tables.tables.actions.create') }}
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <x-card class="mb-4">
        <form method="get" action="{{ route('admin.tables.tables.index', ['hall' => (int) $hall->id]) }}" class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div class="flex flex-wrap gap-2">
                @if ($canViewArchive)
                    @foreach (['active', 'archived', 'all'] as $mode)
                        <x-button
                            :href="route('admin.tables.tables.index', ['hall' => (int) $hall->id, 'archive_mode' => $mode, 'show_inactive' => $includeInactive ? 1 : null])"
                            :variant="$archiveMode === $mode ? 'secondary' : 'outline-secondary'"
                            size="sm"
                        >
                            {{ __('tables.tables.archive_modes.'.$mode) }}
                        </x-button>
                    @endforeach
                @endif
            </div>

            <label class="inline-flex items-center gap-2 text-sm font-semibold text-smartrest-ink">
                <input
                    type="checkbox"
                    name="show_inactive"
                    value="1"
                    class="rounded border-slate-300 text-smartrest-success focus:ring-smartrest-success"
                    @checked($includeInactive)
                    onchange="this.form.submit()"
                >
                {{ __('tables.tables.actions.show_inactive') }}
            </label>
            @if ($canViewArchive)
                <input type="hidden" name="archive_mode" value="{{ $archiveMode }}">
            @endif
        </form>
    </x-card>

    <x-card>
        @if ($tables->isEmpty())
            <div class="rounded-sr-brand border border-dashed border-slate-200 bg-slate-50 px-5 py-10 text-center">
                <h2 class="text-lg font-bold text-smartrest-ink">{{ __('tables.tables.empty.title') }}</h2>
                <p class="mt-1 text-sm text-smartrest-muted">{{ __('tables.tables.empty.body') }}</p>
                <x-button :href="route('admin.tables.tables.create', ['hall' => (int) $hall->id])" class="mt-4">
                    {{ __('tables.tables.actions.create') }}
                </x-button>
            </div>
        @else
            <x-table>
                <thead>
                    <tr>
                        <th>{{ __('tables.tables.fields.name') }}</th>
                        <th>{{ __('tables.tables.fields.type') }}</th>
                        <th>{{ __('tables.tables.fields.shape') }}</th>
                        <th>{{ __('tables.tables.fields.hdm_department') }}</th>
                        <th>{{ __('tables.tables.fields.is_delivery') }}</th>
                        <th>{{ __('tables.tables.fields.sort_order') }}</th>
                        <th>{{ __('tables.tables.fields.status') }}</th>
                        <th class="text-right">{{ __('tables.tables.fields.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($tables as $table)
                        <tr>
                            <td>
                                <div class="font-semibold text-smartrest-ink">
                                    {{ $table->translatedName()->forLocale(app()->getLocale(), 'en') }}
                                </div>
                                @if ($table->trashed())
                                    <span class="mt-1 inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">
                                        {{ __('tables.tables.status.archived') }}
                                    </span>
                                @endif
                            </td>
                            <td>{{ __('tables.tables.types.'.$table->type) }}</td>
                            <td>{{ __('tables.tables.shapes.'.$table->shape) }}</td>
                            <td>{{ $table->hdm_department ?? __('tables.tables.empty_value') }}</td>
                            <td>{{ $table->is_delivery ? __('tables.tables.values.yes') : __('tables.tables.values.no') }}</td>
                            <td>{{ $table->sort_order }}</td>
                            <td>
                                <x-badge-status
                                    :active="(bool) $table->active"
                                    :active-label="__('tables.tables.status.active')"
                                    :inactive-label="__('tables.tables.status.inactive')"
                                />
                            </td>
                            <td>
                                <div class="flex flex-wrap justify-end gap-2">
                                    @if (! $table->trashed())
                                        <x-button :href="route('admin.tables.tables.edit', ['hall' => (int) $hall->id, 'table' => (int) $table->id])" variant="outline-secondary" size="sm">
                                            {{ __('tables.tables.actions.edit') }}
                                        </x-button>
                                        <x-confirm-modal
                                            id="archive_table_{{ (int) $table->id }}"
                                            :action="route('admin.tables.tables.destroy', ['hall' => (int) $hall->id, 'table' => (int) $table->id])"
                                            :title="__('tables.tables.confirm.archive_title')"
                                            :message="__('tables.tables.confirm.archive_message')"
                                            :trigger-label="__('tables.tables.actions.archive')"
                                            :confirm-label="__('tables.tables.actions.archive')"
                                        />
                                    @elseif ($canViewArchive)
                                        <form method="post" action="{{ route('admin.tables.tables.restore', ['hall' => (int) $hall->id, 'table' => (int) $table->id]) }}">
                                            @csrf
                                            <x-button type="submit" variant="outline-primary" size="sm">
                                                {{ __('tables.tables.actions.restore') }}
                                            </x-button>
                                        </form>
                                        <x-confirm-modal
                                            id="force_delete_table_{{ (int) $table->id }}"
                                            :action="route('admin.tables.tables.force-delete', ['hall' => (int) $hall->id, 'table' => (int) $table->id])"
                                            :title="__('tables.tables.confirm.force_delete_title')"
                                            :message="__('tables.tables.confirm.force_delete_message')"
                                            :trigger-label="__('tables.tables.actions.force_delete')"
                                            :confirm-label="__('tables.tables.actions.force_delete')"
                                        />
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-table>

            <div class="mt-4">
                {{ $tables->withQueryString()->links() }}
            </div>
        @endif
    </x-card>
@endsection
