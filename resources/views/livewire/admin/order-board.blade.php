<?php

declare(strict_types=1);

/**
 * @var list<array{
 *     id: int,
 *     name: string,
 *     color: string,
 *     sort_order: int,
 *     tables: list<array{
 *         id: int,
 *         name: string,
 *         type: string,
 *         shape: string,
 *         sort_order: int,
 *         occupied: bool,
 *         occupancy: array{
 *             order_id: int,
 *             workspace_url: string,
 *             client_count: int,
 *             opened_at: string,
 *             duration_minutes: int,
 *             total: string
 *         }|null
 *     }>
 * }> $halls
 */
?>

<div wire:poll.15s>
    <x-page-header
        :eyebrow="__('orders.board.eyebrow')"
        :title="__('orders.board.heading')"
        :subtitle="__('orders.board.subtitle')"
    />

    @if ($statusMessage)
        <div class="mb-5 rounded-sr-card border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-900">
            {{ $statusMessage }}
        </div>
    @endif

    @if ($errorMessage)
        <div class="mb-5 rounded-sr-card border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-900">
            {{ $errorMessage }}
        </div>
    @endif

    @if ($halls === [])
        <x-card>
            <div class="rounded-sr-brand border border-dashed border-slate-200 bg-slate-50 px-5 py-10 text-center">
                <h2 class="text-lg font-bold text-smartrest-ink">{{ __('orders.board.empty_title') }}</h2>
                <p class="mt-1 text-sm text-smartrest-muted">{{ __('orders.board.empty_body') }}</p>
            </div>
        </x-card>
    @else
        <div class="space-y-5">
            @foreach ($halls as $hall)
                <section class="overflow-hidden rounded-[1.5rem] border border-slate-200 bg-white shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 bg-slate-50/80 px-4 py-4 sm:px-5">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="h-4 w-4 shrink-0 rounded-full ring-4 ring-white shadow-sm" style="background-color: {{ $hall['color'] }}"></span>
                            <div class="min-w-0">
                                <h2 class="truncate text-lg font-black text-smartrest-ink">{{ $hall['name'] }}</h2>
                                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-smartrest-muted">
                                    {{ count($hall['tables']) }} {{ __('tables.halls.actions.tables') }}
                                </p>
                            </div>
                        </div>
                    </div>

                    @if ($hall['tables'] === [])
                        <div class="px-5 py-8 text-sm font-semibold text-smartrest-muted">
                            {{ __('orders.board.hall_empty') }}
                        </div>
                    @else
                        <div class="grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
                            @foreach ($hall['tables'] as $table)
                                @php
                                    $tileClass = 'rounded-[1.35rem] border p-4 shadow-sm transition ';
                                    $tileClass .= $table['occupied']
                                        ? 'border-amber-200 bg-gradient-to-br from-amber-50 via-white to-orange-50'
                                        : 'border-emerald-200 bg-gradient-to-br from-emerald-50 via-white to-slate-50 hover:-translate-y-0.5 hover:border-emerald-300 hover:shadow-md focus:outline-none focus:ring-4 focus:ring-emerald-100';
                                @endphp

                                @if ($table['occupied'])
                                    <a href="{{ $table['occupancy']['workspace_url'] }}" class="{{ $tileClass }} block text-left no-underline hover:-translate-y-0.5 hover:border-amber-300 hover:shadow-md focus:outline-none focus:ring-4 focus:ring-amber-100">
                                @else
                                    <button type="button" wire:click="selectTable({{ $table['id'] }})" class="{{ $tileClass }} text-left">
                                @endif
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <h3 class="truncate text-xl font-black text-smartrest-ink">{{ $table['name'] }}</h3>
                                            <div class="mt-2 flex flex-wrap gap-1.5 text-xs font-bold uppercase tracking-[0.12em] text-slate-500">
                                                <span class="rounded-full bg-white/80 px-2 py-1 ring-1 ring-slate-200">{{ __('tables.tables.types.'.$table['type']) }}</span>
                                                <span class="rounded-full bg-white/80 px-2 py-1 ring-1 ring-slate-200">{{ __('tables.tables.shapes.'.$table['shape']) }}</span>
                                            </div>
                                        </div>
                                        <span class="rounded-full px-3 py-1 text-xs font-black uppercase tracking-[0.12em] {{ $table['occupied'] ? 'bg-amber-200 text-amber-950' : 'bg-emerald-200 text-emerald-950' }}">
                                            {{ $table['occupied'] ? __('orders.board.occupied') : __('orders.board.free') }}
                                        </span>
                                    </div>

                                    @if ($table['occupancy'] !== null)
                                        <dl class="mt-4 grid gap-2 text-sm">
                                            <div class="flex items-center justify-between gap-3">
                                                <dt class="font-semibold text-smartrest-muted">{{ __('orders.board.order_number', ['id' => $table['occupancy']['order_id']]) }}</dt>
                                                <dd class="font-black text-smartrest-ink">{{ __('orders.board.guests', ['count' => $table['occupancy']['client_count']]) }}</dd>
                                            </div>
                                            <div class="flex items-center justify-between gap-3">
                                                <dt class="font-semibold text-smartrest-muted">{{ __('orders.board.opened', ['time' => $table['occupancy']['opened_at']]) }}</dt>
                                                <dd class="font-bold text-smartrest-ink">{{ __('orders.board.duration', ['minutes' => $table['occupancy']['duration_minutes']]) }}</dd>
                                            </div>
                                            <div class="flex items-center justify-between gap-3 rounded-2xl bg-white/80 px-3 py-2 ring-1 ring-black/5">
                                                <dt class="font-semibold text-smartrest-muted">{{ __('orders.board.total') }}</dt>
                                                <dd class="font-black text-smartrest-ink">{{ $table['occupancy']['total'] }}</dd>
                                            </div>
                                        </dl>
                                    @else
                                        <div class="mt-4 rounded-2xl border border-dashed border-emerald-200 bg-white/70 px-3 py-4 text-sm font-semibold text-emerald-900">
                                            {{ __('orders.board.action_open') }}
                                        </div>
                                    @endif

                                @if ($table['occupied'])
                                    </a>
                                @else
                                    </button>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </section>
            @endforeach
        </div>
    @endif

    @if ($openModalVisible)
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-slate-950/40 px-4 py-6 sm:items-center" role="dialog" aria-modal="true" aria-labelledby="open-order-title">
            <div class="w-full max-w-lg rounded-[1.5rem] bg-white p-5 shadow-2xl ring-1 ring-black/5 sm:p-6">
                <div>
                    <p class="text-xs font-black uppercase tracking-[0.18em] text-emerald-700">{{ __('orders.board.form.eyebrow') }}</p>
                    <h2 id="open-order-title" class="mt-2 text-2xl font-black text-smartrest-ink">{{ __('orders.board.modal_title') }}</h2>
                    <p class="mt-1 text-sm font-medium text-smartrest-muted">{{ __('orders.board.modal_subtitle') }}</p>
                </div>

                <div class="mt-5 space-y-4">
                    <div>
                        <label for="order-board-guest-count" class="text-sm font-bold text-smartrest-ink">{{ __('orders.board.form.guests') }}</label>
                        <input
                            id="order-board-guest-count"
                            type="number"
                            min="1"
                            wire:model="guestCount"
                            class="mt-2 w-full rounded-sr-card border border-slate-200 px-4 py-3 text-base font-semibold text-smartrest-ink shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-4 focus:ring-emerald-100"
                        >
                        @error('guestCount')
                            <p class="mt-1 text-sm font-semibold text-rose-700">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="order-board-comment" class="text-sm font-bold text-smartrest-ink">{{ __('orders.board.form.comment') }}</label>
                        <textarea
                            id="order-board-comment"
                            wire:model="comment"
                            rows="3"
                            maxlength="1000"
                            placeholder="{{ __('orders.board.form.comment_placeholder') }}"
                            class="mt-2 w-full rounded-sr-card border border-slate-200 px-4 py-3 text-base font-medium text-smartrest-ink shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-4 focus:ring-emerald-100"
                        ></textarea>
                        @error('comment')
                            <p class="mt-1 text-sm font-semibold text-rose-700">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <button
                        type="button"
                        wire:click="cancelOpen"
                        class="rounded-sr-card border border-slate-200 px-5 py-3 text-sm font-black text-smartrest-ink transition hover:bg-slate-50"
                    >
                        {{ __('orders.board.buttons.cancel') }}
                    </button>
                    <button
                        type="button"
                        wire:click="openOrder"
                        class="rounded-sr-card bg-emerald-600 px-5 py-3 text-sm font-black text-white shadow-sm transition hover:bg-emerald-700 focus:outline-none focus:ring-4 focus:ring-emerald-100"
                    >
                        {{ __('orders.board.buttons.open') }}
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
