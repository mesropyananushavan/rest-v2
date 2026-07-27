<?php

declare(strict_types=1);

/**
 * @var array{
 *     id: int,
 *     type: string,
 *     status: string,
 *     table_id: int,
 *     opened_at: string,
 *     client_count: int,
 *     comment: string|null,
 *     subtotal: string,
 *     discount: string,
 *     total: string,
 *     groups: list<array{id: int|null, name: string, items: list<array{id: int, name: string, qty: int, unit_price: string, discount: string, total: string}>}>
 * } $order
 */
?>

<div>
    <x-page-header
        :eyebrow="__('orders.workspace.eyebrow')"
        :title="__('orders.workspace.heading', ['id' => $order['id']])"
        :subtitle="__('orders.workspace.subtitle', ['table' => $order['table_id']])"
    >
        <x-slot:actions>
            <x-button :href="route('admin.orders.board')" variant="outline-secondary" size="sm">
                {{ __('orders.workspace.back_to_board') }}
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="space-y-5">
            <x-card :title="__('orders.workspace.items_title')">
                @if ($order['groups'] === [])
                    <div class="rounded-sr-brand border border-dashed border-slate-200 bg-slate-50 px-5 py-8 text-center text-sm font-semibold text-smartrest-muted">
                        {{ __('orders.workspace.no_items') }}
                    </div>
                @else
                    <div class="space-y-4">
                        @foreach ($order['groups'] as $group)
                            <section class="overflow-hidden rounded-[1.25rem] border border-slate-200">
                                <div class="flex items-center justify-between gap-3 bg-slate-50 px-4 py-3">
                                    <h2 class="text-sm font-black uppercase tracking-[0.14em] text-smartrest-ink">{{ $group['name'] }}</h2>
                                    <span class="rounded-full bg-white px-2.5 py-1 text-xs font-bold text-smartrest-muted ring-1 ring-slate-200">
                                        {{ count($group['items']) }}
                                    </span>
                                </div>

                                @if ($group['items'] === [])
                                    <div class="px-4 py-5 text-sm font-semibold text-smartrest-muted">
                                        {{ __('orders.workspace.no_group_items') }}
                                    </div>
                                @else
                                    <x-table>
                                        <thead>
                                            <tr>
                                                <th>{{ __('orders.workspace.fields.item') }}</th>
                                                <th>{{ __('orders.workspace.fields.qty') }}</th>
                                                <th>{{ __('orders.workspace.fields.unit_price') }}</th>
                                                <th>{{ __('orders.workspace.fields.line_discount') }}</th>
                                                <th>{{ __('orders.workspace.fields.line_total') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100">
                                            @foreach ($group['items'] as $item)
                                                <tr>
                                                    <td class="font-bold text-smartrest-ink">{{ $item['name'] }}</td>
                                                    <td>{{ $item['qty'] }}</td>
                                                    <td>{{ $item['unit_price'] }}</td>
                                                    <td>{{ $item['discount'] }}</td>
                                                    <td class="font-black text-smartrest-ink">{{ $item['total'] }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </x-table>
                                @endif
                            </section>
                        @endforeach
                    </div>
                @endif
            </x-card>
        </div>

        <aside class="space-y-5">
            <x-card :title="__('orders.workspace.summary_title')">
                <dl class="grid gap-3 text-sm">
                    <div class="flex items-center justify-between gap-3">
                        <dt class="font-semibold text-smartrest-muted">{{ __('orders.workspace.fields.order') }}</dt>
                        <dd class="font-black text-smartrest-ink">{{ __('orders.board.order_number', ['id' => $order['id']]) }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="font-semibold text-smartrest-muted">{{ __('orders.workspace.fields.table') }}</dt>
                        <dd class="font-black text-smartrest-ink">{{ __('orders.workspace.table_label', ['id' => $order['table_id']]) }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="font-semibold text-smartrest-muted">{{ __('orders.workspace.fields.type') }}</dt>
                        <dd class="font-black text-smartrest-ink">{{ __('orders.types.'.$order['type']) }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="font-semibold text-smartrest-muted">{{ __('orders.workspace.fields.status') }}</dt>
                        <dd>
                            <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-black uppercase tracking-[0.12em] text-emerald-900">
                                {{ __('orders.status.'.$order['status']) }}
                            </span>
                        </dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="font-semibold text-smartrest-muted">{{ __('orders.workspace.fields.opened_at') }}</dt>
                        <dd class="font-bold text-smartrest-ink">{{ $order['opened_at'] }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="font-semibold text-smartrest-muted">{{ __('orders.workspace.fields.guests') }}</dt>
                        <dd class="font-bold text-smartrest-ink">{{ __('orders.board.guests', ['count' => $order['client_count']]) }}</dd>
                    </div>
                </dl>
            </x-card>

            @if ($order['comment'] !== null)
                <x-card :title="__('orders.workspace.comment_title')">
                    <p class="whitespace-pre-line text-sm font-medium text-smartrest-ink">{{ $order['comment'] }}</p>
                </x-card>
            @endif

            <x-card :title="__('orders.workspace.totals_title')">
                <dl class="grid gap-3 text-sm">
                    <div class="flex items-center justify-between gap-3">
                        <dt class="font-semibold text-smartrest-muted">{{ __('orders.workspace.fields.subtotal') }}</dt>
                        <dd class="font-bold text-smartrest-ink">{{ $order['subtotal'] }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="font-semibold text-smartrest-muted">{{ __('orders.workspace.fields.order_discount') }}</dt>
                        <dd class="font-bold text-smartrest-ink">{{ $order['discount'] }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3 rounded-2xl bg-emerald-50 px-3 py-3 ring-1 ring-emerald-100">
                        <dt class="font-black text-emerald-950">{{ __('orders.workspace.fields.total') }}</dt>
                        <dd class="text-xl font-black text-emerald-950">{{ $order['total'] }}</dd>
                    </div>
                </dl>
            </x-card>
        </aside>
    </div>
</div>
