<?php

declare(strict_types=1);

use App\Modules\Payments\Infrastructure\Models\Cashbox;
use Illuminate\Pagination\LengthAwarePaginator;

/** @var LengthAwarePaginator<int, Cashbox> $cashboxes */
/** @var int $activeCashboxCount */
/** @var bool $includeInactive */
?>

@extends('layouts.admin')

@section('title', __('payments.cashboxes.index.title'))

@section('content')
    <x-page-header
        :eyebrow="__('payments.cashboxes.index.eyebrow')"
        :title="__('payments.cashboxes.index.heading')"
        :subtitle="__('payments.cashboxes.index.subtitle')"
    >
        <x-slot:actions>
            <x-button :href="route('admin.payments.cashboxes.create')" size="sm">
                {{ __('payments.cashboxes.actions.create') }}
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <x-card class="mb-4">
        <form method="get" action="{{ route('admin.payments.cashboxes.index') }}" class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-smartrest-muted">{{ __('payments.cashboxes.index.lifecycle_note') }}</p>
            <label class="inline-flex items-center gap-2 text-sm font-semibold text-smartrest-ink">
                <input
                    type="checkbox"
                    name="active_only"
                    value="1"
                    class="rounded border-slate-300 text-smartrest-success focus:ring-smartrest-success"
                    @checked(! $includeInactive)
                    onchange="this.form.submit()"
                >
                {{ __('payments.cashboxes.actions.active_only') }}
            </label>
        </form>
    </x-card>

    <x-card>
        @if ($cashboxes->isEmpty())
            <div class="rounded-sr-brand border border-dashed border-slate-200 bg-slate-50 px-5 py-10 text-center">
                <h2 class="text-lg font-bold text-smartrest-ink">{{ __('payments.cashboxes.empty.title') }}</h2>
                <p class="mt-1 text-sm text-smartrest-muted">{{ __('payments.cashboxes.empty.body') }}</p>
                <x-button :href="route('admin.payments.cashboxes.create')" class="mt-4">
                    {{ __('payments.cashboxes.actions.create') }}
                </x-button>
            </div>
        @else
            <x-table>
                <thead>
                    <tr>
                        <th>{{ __('payments.cashboxes.fields.name') }}</th>
                        <th>{{ __('payments.cashboxes.fields.active') }}</th>
                        <th>{{ __('payments.cashboxes.fields.default') }}</th>
                        <th class="text-right">{{ __('payments.cashboxes.fields.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($cashboxes as $cashbox)
                        <tr>
                            <td>
                                <div class="font-semibold text-smartrest-ink">{{ $cashbox->name }}</div>
                            </td>
                            <td>
                                <x-badge-status
                                    :active="(bool) $cashbox->is_active"
                                    :active-label="__('payments.cashboxes.status.active')"
                                    :inactive-label="__('payments.cashboxes.status.inactive')"
                                />
                            </td>
                            <td>
                                @if ($cashbox->is_default)
                                    <span class="inline-flex rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-800">
                                        {{ __('payments.cashboxes.status.default') }}
                                    </span>
                                @else
                                    <span class="text-sm text-smartrest-muted">{{ __('payments.cashboxes.status.not_default') }}</span>
                                @endif
                            </td>
                            <td>
                                <div class="flex flex-wrap justify-end gap-2">
                                    <x-button :href="route('admin.payments.cashboxes.edit', ['cashbox' => (int) $cashbox->id])" variant="outline-secondary" size="sm">
                                        {{ __('payments.cashboxes.actions.edit') }}
                                    </x-button>

                                    @if ($cashbox->is_active && ! $cashbox->is_default)
                                        <form method="post" action="{{ route('admin.payments.cashboxes.default', ['cashbox' => (int) $cashbox->id]) }}">
                                            @csrf
                                            <x-button type="submit" variant="outline-primary" size="sm">
                                                {{ __('payments.cashboxes.actions.make_default') }}
                                            </x-button>
                                        </form>
                                    @endif

                                    @if ($cashbox->is_active)
                                        @if ($cashbox->is_default && $activeCashboxCount > 1)
                                            <span class="inline-flex items-center rounded-sr-brand bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-800">
                                                {{ __('payments.cashboxes.actions.select_replacement_first') }}
                                            </span>
                                        @else
                                            <x-confirm-modal
                                                id="deactivate_cashbox_{{ (int) $cashbox->id }}"
                                                method="post"
                                                :action="route('admin.payments.cashboxes.deactivate', ['cashbox' => (int) $cashbox->id])"
                                                :title="__('payments.cashboxes.confirm.deactivate_title')"
                                                :message="__('payments.cashboxes.confirm.deactivate_message')"
                                                :trigger-label="__('payments.cashboxes.actions.deactivate')"
                                                :confirm-label="__('payments.cashboxes.actions.deactivate')"
                                            />
                                        @endif
                                    @else
                                        <form method="post" action="{{ route('admin.payments.cashboxes.activate', ['cashbox' => (int) $cashbox->id]) }}">
                                            @csrf
                                            <x-button type="submit" variant="outline-primary" size="sm">
                                                {{ __('payments.cashboxes.actions.activate') }}
                                            </x-button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-table>

            <div class="mt-4">
                {{ $cashboxes->withQueryString()->links() }}
            </div>
        @endif
    </x-card>
@endsection
