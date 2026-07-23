<?php

declare(strict_types=1);

use App\Modules\Tables\Infrastructure\Models\Hall;
use Illuminate\Pagination\LengthAwarePaginator;

/** @var LengthAwarePaginator<int, Hall> $halls */
/** @var 'active'|'archived'|'all' $archiveMode */
/** @var bool $canViewArchive */
/** @var bool $includeInactive */
?>

@extends('layouts.admin')

@section('title', __('tables.halls.index.title'))

@section('content')
    <x-page-header
        :eyebrow="__('tables.halls.index.eyebrow')"
        :title="__('tables.halls.index.heading')"
        :subtitle="__('tables.halls.index.subtitle')"
    >
        <x-slot:actions>
            <x-button :href="route('admin.tables.halls.create')" size="sm">
                {{ __('tables.halls.actions.create') }}
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <x-card class="mb-4">
        <form method="get" action="{{ route('admin.tables.halls.index') }}" class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div class="flex flex-wrap gap-2">
                @if ($canViewArchive)
                    @foreach (['active', 'archived', 'all'] as $mode)
                        <x-button
                            :href="route('admin.tables.halls.index', ['archive_mode' => $mode, 'show_inactive' => $includeInactive ? 1 : null])"
                            :variant="$archiveMode === $mode ? 'secondary' : 'outline-secondary'"
                            size="sm"
                        >
                            {{ __('tables.halls.archive_modes.'.$mode) }}
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
                {{ __('tables.halls.actions.show_inactive') }}
            </label>
            @if ($canViewArchive)
                <input type="hidden" name="archive_mode" value="{{ $archiveMode }}">
            @endif
        </form>
    </x-card>

    <x-card>
        @if ($halls->isEmpty())
            <div class="rounded-sr-brand border border-dashed border-slate-200 bg-slate-50 px-5 py-10 text-center">
                <h2 class="text-lg font-bold text-smartrest-ink">{{ __('tables.halls.empty.title') }}</h2>
                <p class="mt-1 text-sm text-smartrest-muted">{{ __('tables.halls.empty.body') }}</p>
                <x-button :href="route('admin.tables.halls.create')" class="mt-4">
                    {{ __('tables.halls.actions.create') }}
                </x-button>
            </div>
        @else
            <x-table>
                <thead>
                    <tr>
                        <th>{{ __('tables.halls.fields.name') }}</th>
                        <th>{{ __('tables.halls.fields.color') }}</th>
                        <th>{{ __('tables.halls.fields.sort_order') }}</th>
                        <th>{{ __('tables.halls.fields.status') }}</th>
                        <th class="text-right">{{ __('tables.halls.fields.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($halls as $hall)
                        <tr>
                            <td>
                                <div class="font-semibold text-smartrest-ink">
                                    {{ $hall->translatedName()->forLocale(app()->getLocale(), 'en') }}
                                </div>
                                @if ($hall->trashed())
                                    <span class="mt-1 inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">
                                        {{ __('tables.halls.status.archived') }}
                                    </span>
                                @endif
                            </td>
                            <td>
                                <span class="inline-flex items-center gap-2">
                                    <span class="h-5 w-5 rounded-full ring-1 ring-black/10" style="background-color: {{ $hall->color }}"></span>
                                    <span class="font-mono text-xs text-slate-600">{{ $hall->color }}</span>
                                </span>
                            </td>
                            <td>{{ $hall->sort_order }}</td>
                            <td>
                                <x-badge-status
                                    :active="(bool) $hall->active"
                                    :active-label="__('tables.halls.status.active')"
                                    :inactive-label="__('tables.halls.status.inactive')"
                                />
                            </td>
                            <td>
                                <div class="flex flex-wrap justify-end gap-2">
                                    @if (! $hall->trashed())
                                        <x-button :href="route('admin.tables.tables.index', ['hall' => (int) $hall->id])" variant="secondary" size="sm">
                                            {{ __('tables.halls.actions.tables') }}
                                        </x-button>
                                        <x-button :href="route('admin.tables.halls.edit', ['hall' => (int) $hall->id])" variant="outline-secondary" size="sm">
                                            {{ __('tables.halls.actions.edit') }}
                                        </x-button>
                                        <x-confirm-modal
                                            id="archive_hall_{{ (int) $hall->id }}"
                                            :action="route('admin.tables.halls.destroy', ['hall' => (int) $hall->id])"
                                            :title="__('tables.halls.confirm.archive_title')"
                                            :message="__('tables.halls.confirm.archive_message')"
                                            :trigger-label="__('tables.halls.actions.archive')"
                                            :confirm-label="__('tables.halls.actions.archive')"
                                        />
                                    @elseif ($canViewArchive)
                                        <form method="post" action="{{ route('admin.tables.halls.restore', ['hall' => (int) $hall->id]) }}">
                                            @csrf
                                            <x-button type="submit" variant="outline-primary" size="sm">
                                                {{ __('tables.halls.actions.restore') }}
                                            </x-button>
                                        </form>
                                        <x-confirm-modal
                                            id="force_delete_hall_{{ (int) $hall->id }}"
                                            :action="route('admin.tables.halls.force-delete', ['hall' => (int) $hall->id])"
                                            :title="__('tables.halls.confirm.force_delete_title')"
                                            :message="__('tables.halls.confirm.force_delete_message')"
                                            :trigger-label="__('tables.halls.actions.force_delete')"
                                            :confirm-label="__('tables.halls.actions.force_delete')"
                                        />
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-table>

            <div class="mt-4">
                {{ $halls->withQueryString()->links() }}
            </div>
        @endif
    </x-card>
@endsection
