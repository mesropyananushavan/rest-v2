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
                                <article class="rounded-[1.35rem] border p-4 shadow-sm transition {{ $table['occupied'] ? 'border-amber-200 bg-gradient-to-br from-amber-50 via-white to-orange-50' : 'border-emerald-200 bg-gradient-to-br from-emerald-50 via-white to-slate-50' }}">
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
                                            {{ __('orders.board.free') }}
                                        </div>
                                    @endif
                                </article>
                            @endforeach
                        </div>
                    @endif
                </section>
            @endforeach
        </div>
    @endif
</div>
