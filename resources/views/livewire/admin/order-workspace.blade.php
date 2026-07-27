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
 * @var array{
 *     search: string,
 *     selected_category_id: int|null,
 *     category_page: int,
 *     has_previous_category_page: bool,
 *     has_more_category_pages: bool,
 *     item_page: int,
 *     has_previous_item_page: bool,
 *     has_more_item_pages: bool,
 *     category_groups: list<array{id: int, name: string, categories: list<array{id: int, name: string, selected: bool}>}>,
 *     items: list<array{id: int, category_id: int, name: string, price: string}>
 * } $menu
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

            <x-card :title="__('orders.workspace.menu_picker.title')" body-class="p-0">
                <div class="border-b border-slate-100 p-4">
                    <label for="order-workspace-menu-search" class="mb-2 block text-sm font-black uppercase tracking-[0.16em] text-green-800">
                        {{ __('orders.workspace.menu_picker.search_label') }}
                    </label>
                    <div class="flex flex-col gap-3 md:flex-row">
                        <input
                            id="order-workspace-menu-search"
                            type="search"
                            wire:model.live.debounce.350ms="menuSearch"
                            class="min-h-12 flex-1 rounded-2xl border border-emerald-200 bg-white px-4 text-base font-semibold text-smartrest-ink shadow-sm outline-none transition placeholder:text-slate-400 focus:border-smartrest-success focus:ring-4 focus:ring-smartrest-success/15"
                            placeholder="{{ __('orders.workspace.menu_picker.search_placeholder') }}"
                        >
                        @if ($menu['search'] !== '')
                            <x-button type="button" variant="outline-secondary" wire:click="clearMenuSearch">
                                {{ __('orders.workspace.menu_picker.clear_search') }}
                            </x-button>
                        @endif
                    </div>
                    <p class="mt-2 text-sm text-green-900/70">{{ __('orders.workspace.menu_picker.help') }}</p>
                </div>

                <div class="grid gap-0 lg:grid-cols-[18rem_minmax(0,1fr)]">
                    <aside class="border-b border-slate-100 p-4 lg:border-b-0 lg:border-r">
                        <div class="mb-3 flex items-center justify-between gap-3">
                            <h2 class="text-xs font-black uppercase tracking-[0.16em] text-smartrest-muted">{{ __('orders.workspace.menu_picker.categories') }}</h2>
                            @if ($menu['selected_category_id'] !== null)
                                <button type="button" wire:click="clearMenuCategory" class="text-xs font-bold text-smartrest-success underline-offset-4 transition hover:text-green-800 hover:underline">
                                    {{ __('orders.workspace.menu_picker.all_items') }}
                                </button>
                            @endif
                        </div>

                        @if ($menu['category_groups'] === [])
                            <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-4 text-sm font-semibold text-smartrest-muted">
                                {{ __('orders.workspace.menu_picker.no_categories') }}
                            </div>
                        @else
                            <div class="space-y-3">
                                @foreach ($menu['category_groups'] as $group)
                                    <section class="rounded-2xl border border-slate-200 bg-white p-3">
                                        <h3 class="truncate text-xs font-black uppercase tracking-[0.14em] text-slate-500">{{ $group['name'] }}</h3>

                                        <div class="mt-2 grid gap-2">
                                            @foreach ($group['categories'] as $category)
                                                <button
                                                    type="button"
                                                    wire:click="selectMenuCategory({{ $category['id'] }})"
                                                    class="rounded-xl border px-3 py-2 text-left text-sm font-bold transition {{ $category['selected'] ? 'border-smartrest-success bg-emerald-50 text-green-900' : 'border-transparent text-smartrest-ink hover:border-slate-200 hover:bg-slate-50' }}"
                                                >
                                                    {{ $category['name'] }}
                                                </button>
                                            @endforeach
                                        </div>
                                    </section>
                                @endforeach
                            </div>

                            @if ($menu['has_previous_category_page'] || $menu['has_more_category_pages'])
                                <div class="mt-4 flex items-center justify-between gap-2 text-sm text-smartrest-muted">
                                    <span>{{ __('orders.workspace.menu_picker.page', ['page' => $menu['category_page']]) }}</span>
                                    <div class="flex gap-2">
                                        <x-button type="button" variant="outline-secondary" size="sm" wire:click="previousMenuCategoryPage" :disabled="! $menu['has_previous_category_page']">
                                            {{ __('orders.workspace.menu_picker.previous') }}
                                        </x-button>
                                        <x-button type="button" variant="outline-secondary" size="sm" wire:click="nextMenuCategoryPage" :disabled="! $menu['has_more_category_pages']">
                                            {{ __('orders.workspace.menu_picker.next') }}
                                        </x-button>
                                    </div>
                                </div>
                            @endif
                        @endif
                    </aside>

                    <section class="p-4">
                        @if ($menu['items'] === [])
                            <div class="rounded-sr-brand border border-dashed border-slate-200 bg-slate-50 px-5 py-8 text-center text-sm font-semibold text-smartrest-muted">
                                {{ __('orders.workspace.menu_picker.no_items') }}
                            </div>
                        @else
                            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                                @foreach ($menu['items'] as $item)
                                    <article class="rounded-[1.2rem] border border-slate-200 bg-white p-4 shadow-sm">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <h3 class="truncate text-base font-black text-smartrest-ink">{{ $item['name'] }}</h3>
                                                <p class="mt-1 text-xs font-semibold uppercase tracking-[0.14em] text-smartrest-muted">
                                                    {{ __('orders.workspace.menu_picker.item_number', ['id' => $item['id']]) }}
                                                </p>
                                            </div>
                                            <span class="shrink-0 rounded-full bg-emerald-50 px-3 py-1 text-sm font-black text-emerald-900 ring-1 ring-emerald-100">
                                                {{ $item['price'] }}
                                            </span>
                                        </div>
                                    </article>
                                @endforeach
                            </div>

                            @if ($menu['has_previous_item_page'] || $menu['has_more_item_pages'])
                                <div class="mt-4 flex items-center justify-between gap-2 border-t border-slate-100 pt-4 text-sm text-smartrest-muted">
                                    <span>{{ __('orders.workspace.menu_picker.page', ['page' => $menu['item_page']]) }}</span>
                                    <div class="flex gap-2">
                                        <x-button type="button" variant="outline-secondary" size="sm" wire:click="previousMenuPage" :disabled="! $menu['has_previous_item_page']">
                                            {{ __('orders.workspace.menu_picker.previous') }}
                                        </x-button>
                                        <x-button type="button" variant="outline-secondary" size="sm" wire:click="nextMenuPage" :disabled="! $menu['has_more_item_pages']">
                                            {{ __('orders.workspace.menu_picker.next') }}
                                        </x-button>
                                    </div>
                                </div>
                            @endif
                        @endif
                    </section>
                </div>
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
