<?php

declare(strict_types=1);

use App\Modules\Menu\Domain\MenuItemImageSlot;
use App\Modules\Menu\Infrastructure\Models\MenuCategory;
use App\Modules\Menu\Infrastructure\Models\MenuItem;
use App\Modules\Menu\Infrastructure\Storage\MenuItemImageUrlResolver;
use App\Support\Money\MoneyFormatter;
use Illuminate\Pagination\LengthAwarePaginator;

/** @var bool $canManageCategories */
/** @var bool $canManageItems */
/** @var bool $canViewArchive */
/** @var 'active'|'archived'|'all' $archiveMode */
/** @var LengthAwarePaginator<int, MenuCategory> $categories */
/** @var LengthAwarePaginator<int, MenuItem>|null $globalResults */
/** @var MenuItemImageUrlResolver $imageUrls */
/** @var bool $isSearching */
/** @var LengthAwarePaginator<int, MenuItem>|null $items */
/** @var MenuCategory|null $selectedCategory */
/** @var int|null $selectedCategoryId */

$locale = app()->getLocale();
?>

<div>
    @if ($statusMessage !== null)
        <div class="mb-4 rounded-sr-brand border border-smartrest-success/20 bg-smartrest-success/10 px-4 py-3 text-sm font-medium text-green-800" role="status">
            {{ $statusMessage }}
        </div>
    @endif

    <x-page-header
        :eyebrow="__('menu.index.eyebrow')"
        :title="__('menu.index.heading')"
        :subtitle="__('menu.index.subtitle')"
    >
        <x-slot:actions>
            @if ($canViewArchive)
                <div class="inline-flex rounded-sr-brand border border-slate-200 bg-white p-1 text-sm font-semibold shadow-sm">
                    @foreach (['active', 'archived', 'all'] as $mode)
                        <button
                            type="button"
                            wire:click="$set('archiveMode', '{{ $mode }}')"
                            class="min-h-9 rounded-[0.7rem] px-3 py-1.5 transition {{ $archiveMode === $mode ? 'bg-smartrest-success text-white shadow-sm' : 'text-slate-600 hover:bg-slate-50 hover:text-smartrest-ink' }}"
                        >
                            {{ __('menu.archive_modes.'.$mode) }}
                        </button>
                    @endforeach
                </div>
            @endif
            @if ($canManageCategories)
                <x-button :href="route('admin.menu.categories.create')" variant="outline-primary">
                    {{ __('menu.actions.create_category') }}
                </x-button>
            @endif
            @if ($canManageItems && $selectedCategoryId !== null)
                <x-button :href="route('admin.menu.items.create', array_filter(['category' => $selectedCategoryId]))">
                    {{ __('menu.actions.create_item') }}
                </x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    <section class="mb-4 rounded-[1.5rem] border border-emerald-200 bg-gradient-to-br from-emerald-50 via-white to-amber-50 p-4 shadow-sm lg:p-5">
        <label for="menu_global_search" class="mb-2 block text-sm font-black uppercase tracking-[0.18em] text-green-800">{{ __('menu.search.global_label') }}</label>
        <div class="flex flex-col gap-3 md:flex-row">
            <input
                id="menu_global_search"
                type="search"
                wire:model.live.debounce.350ms="search"
                class="min-h-14 flex-1 rounded-2xl border border-emerald-200 bg-white px-4 text-lg font-semibold text-smartrest-ink shadow-sm outline-none transition placeholder:text-slate-400 focus:border-smartrest-success focus:ring-4 focus:ring-smartrest-success/15"
                placeholder="{{ __('menu.search.global_placeholder') }}"
            >
            @if ($isSearching)
                <x-button type="button" variant="outline-secondary" wire:click="clearSearch">
                    {{ __('menu.actions.reset_search') }}
                </x-button>
            @endif
        </div>
        <p class="mt-2 text-sm text-green-900/70">{{ __('menu.search.global_help') }}</p>
    </section>

    <div class="grid gap-4 xl:grid-cols-[22rem_minmax(0,1fr)]">
        <aside class="xl:sticky xl:top-4 xl:self-start">
            <x-card :title="__('menu.categories.heading')" :count="$categories->total()" body-class="p-0">
                <div class="border-b border-slate-100 p-3">
                    <input
                        type="search"
                        wire:model.live.debounce.300ms="categorySearch"
                        class="block w-full rounded-sr-control border border-slate-200 bg-white px-3 py-2 text-sm text-smartrest-text shadow-sm outline-none transition placeholder:text-slate-400 focus:border-smartrest-success focus:ring-4 focus:ring-smartrest-success/10"
                        placeholder="{{ __('menu.search.categories_placeholder') }}"
                    >
                </div>

                @php
                    $categoryGroups = $categories->getCollection()->groupBy(fn (MenuCategory $category): int => (int) $category->parent_id);
                @endphp

                <div class="max-h-[55vh] overflow-y-auto p-2 md:max-h-[24rem] xl:max-h-[calc(100vh-18rem)]">
                    @forelse ($categoryGroups as $subcategories)
                        @php
                            /** @var \Illuminate\Support\Collection<int, MenuCategory> $subcategories */
                            $rootCategory = $subcategories->first()?->parent;
                        @endphp

                        @if ($rootCategory instanceof MenuCategory)
                            <section class="mb-3 rounded-3xl border border-slate-200 bg-white/80 p-2">
                                <div class="rounded-2xl bg-slate-50 px-3 py-3">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <div class="truncate text-xs font-black uppercase tracking-[0.18em] text-slate-500">{{ $rootCategory->translatedName()->forLocale($locale) }}</div>
                                            <div class="mt-1 flex flex-wrap items-center gap-1 text-xs text-smartrest-muted">
                                                @if (! $rootCategory->active)
                                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 font-semibold text-slate-600">{{ __('menu.status.inactive') }}</span>
                                                @endif
                                                @if ($rootCategory->trashed() && $canViewArchive)
                                                    <span class="rounded-full bg-amber-100 px-2 py-0.5 font-semibold text-amber-800">{{ __('menu.status.archived') }}</span>
                                                @endif
                                            </div>
                                        </div>
                                        <span class="rounded-full bg-white px-2 py-1 text-xs font-bold text-slate-500 ring-1 ring-slate-200">#{{ (int) $rootCategory->id }}</span>
                                    </div>

                                    @include('livewire.admin.menu.partials.category-actions', [
                                        'canManageCategories' => $canManageCategories,
                                        'canViewArchive' => $canViewArchive,
                                        'category' => $rootCategory,
                                    ])
                                </div>

                                <div class="mt-2 space-y-2">
                                    @foreach ($subcategories as $category)
                                        <div class="rounded-2xl border px-3 py-3 transition {{ $selectedCategoryId === (int) $category->id ? 'border-smartrest-success bg-emerald-50 shadow-sm' : 'border-transparent hover:border-slate-200 hover:bg-slate-50' }}">
                                            <button
                                                type="button"
                                                wire:click="selectCategory({{ (int) $category->id }})"
                                                class="flex w-full items-start justify-between gap-3 text-left"
                                            >
                                                <span class="min-w-0">
                                                    <span class="block truncate text-sm font-black text-smartrest-ink">{{ $category->translatedName()->forLocale($locale) }}</span>
                                                    <span class="mt-1 flex flex-wrap items-center gap-1 text-xs text-smartrest-muted">
                                                        @if (! $category->active)
                                                            <span class="rounded-full bg-slate-100 px-2 py-0.5 font-semibold text-slate-600">{{ __('menu.status.inactive') }}</span>
                                                        @endif
                                                        @if ($category->trashed() && $canViewArchive)
                                                            <span class="rounded-full bg-amber-100 px-2 py-0.5 font-semibold text-amber-800">{{ __('menu.status.archived') }}</span>
                                                        @endif
                                                    </span>
                                                </span>
                                                <span class="rounded-full bg-white px-2 py-1 text-xs font-bold text-slate-500 ring-1 ring-slate-200">#{{ (int) $category->id }}</span>
                                            </button>

                                            @include('livewire.admin.menu.partials.category-actions', [
                                                'canManageCategories' => $canManageCategories,
                                                'canViewArchive' => $canViewArchive,
                                                'category' => $category,
                                            ])
                                        </div>
                                    @endforeach
                                </div>
                            </section>
                        @endif
                    @empty
                        <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-4 text-center">
                            <div class="text-sm font-black text-smartrest-ink">{{ __('menu.empty.no_categories_title') }}</div>
                            <p class="mt-1 text-sm text-smartrest-muted">{{ __('menu.empty.no_categories_body') }}</p>
                            @if ($canManageCategories)
                                <x-button :href="route('admin.menu.categories.create')" size="sm" class="mt-3">
                                    {{ __('menu.actions.create_first_category') }}
                                </x-button>
                            @endif
                        </div>
                    @endforelse
                </div>

                @if ($categories->hasPages())
                    <div class="flex items-center justify-between gap-2 border-t border-slate-100 p-3 text-sm text-smartrest-muted">
                        <span>{{ __('menu.pagination.page_of', ['page' => $categories->currentPage(), 'pages' => $categories->lastPage()]) }}</span>
                        <div class="flex gap-2">
                            <x-button type="button" variant="outline-secondary" size="sm" wire:click="previousCategoryPage" :disabled="$categories->onFirstPage()">
                                {{ __('menu.pagination.previous') }}
                            </x-button>
                            <x-button type="button" variant="outline-secondary" size="sm" wire:click="nextCategoryPage" :disabled="! $categories->hasMorePages()">
                                {{ __('menu.pagination.next') }}
                            </x-button>
                        </div>
                    </div>
                @endif
            </x-card>
        </aside>

        <section>
            @if ($isSearching)
                <x-card :title="__('menu.search.results_heading')" :count="$globalResults?->total() ?? 0" body-class="p-0">
                    @if ($globalResults !== null && $globalResults->count() > 0)
                        @include('livewire.admin.menu.partials.item-list', [
                            'canManageItems' => $canManageItems,
                            'canViewArchive' => $canViewArchive,
                            'imageUrls' => $imageUrls,
                            'items' => $globalResults,
                            'locale' => $locale,
                            'showCategory' => true,
                        ])

                        @include('livewire.admin.menu.partials.item-pagination', [
                            'items' => $globalResults,
                            'nextMethod' => 'nextSearchPage',
                            'previousMethod' => 'previousSearchPage',
                        ])
                    @else
                        <div class="p-8 text-center">
                            <div class="text-lg font-black text-smartrest-ink">{{ __('menu.empty.search_title') }}</div>
                            <p class="mt-2 text-sm text-smartrest-muted">{{ __('menu.empty.search_body') }}</p>
                            <x-button type="button" variant="outline-secondary" wire:click="clearSearch" class="mt-4">
                                {{ __('menu.actions.reset_search') }}
                            </x-button>
                        </div>
                    @endif
                </x-card>
            @elseif ($selectedCategory === null)
                <x-card>
                    <div class="p-8 text-center">
                        <div class="text-lg font-black text-smartrest-ink">{{ __('menu.empty.no_categories_title') }}</div>
                        <p class="mt-2 text-sm text-smartrest-muted">{{ __('menu.empty.no_categories_body') }}</p>
                        @if ($canManageCategories)
                            <x-button :href="route('admin.menu.categories.create')" class="mt-4">
                                {{ __('menu.actions.create_first_category') }}
                            </x-button>
                        @endif
                    </div>
                </x-card>
            @else
                <x-card :title="($selectedCategory->parent?->translatedName()->forLocale($locale) === null ? '' : $selectedCategory->parent?->translatedName()->forLocale($locale).' / ').$selectedCategory->translatedName()->forLocale($locale)" :count="$items?->total() ?? 0" body-class="p-0">
                    <div class="flex flex-col gap-3 border-b border-slate-100 p-4 md:flex-row md:items-center md:justify-between">
                        <div>
                            <div class="text-sm font-semibold text-smartrest-muted">{{ __('menu.items.category_items') }}</div>
                            <div class="mt-1 flex flex-wrap items-center gap-2">
                                <x-badge-status
                                    :active="$selectedCategory->active"
                                    :active-label="__('menu.status.active')"
                                    :inactive-label="__('menu.status.inactive')"
                                />
                                @if ($selectedCategory->trashed() && $canViewArchive)
                                    <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800 ring-1 ring-amber-200">{{ __('menu.status.archived') }}</span>
                                @endif
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <label class="inline-flex min-h-10 items-center gap-2 rounded-sr-brand border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm">
                                <input type="checkbox" wire:model.live="showInactive" class="h-4 w-4 rounded border-slate-300 text-smartrest-success focus:ring-smartrest-success/20">
                                {{ __('menu.actions.show_inactive') }}
                            </label>
                            @if ($canManageItems)
                                <x-button :href="route('admin.menu.items.create', ['category' => $selectedCategoryId])" size="sm">
                                    {{ __('menu.actions.add_first_item') }}
                                </x-button>
                            @endif
                        </div>
                    </div>

                    @if ($items !== null && $items->count() > 0)
                        @include('livewire.admin.menu.partials.item-list', [
                            'canManageItems' => $canManageItems,
                            'canViewArchive' => $canViewArchive,
                            'imageUrls' => $imageUrls,
                            'items' => $items,
                            'locale' => $locale,
                            'showCategory' => false,
                        ])

                        @include('livewire.admin.menu.partials.item-pagination', [
                            'items' => $items,
                            'nextMethod' => 'nextItemPage',
                            'previousMethod' => 'previousItemPage',
                        ])
                    @else
                        <div class="p-8 text-center">
                            <div class="text-lg font-black text-smartrest-ink">{{ __('menu.empty.no_items_title') }}</div>
                            <p class="mt-2 text-sm text-smartrest-muted">{{ __('menu.empty.no_items_body') }}</p>
                            @if ($canManageItems)
                                <x-button :href="route('admin.menu.items.create', ['category' => $selectedCategoryId])" class="mt-4">
                                    {{ __('menu.actions.add_first_item') }}
                                </x-button>
                            @endif
                        </div>
                    @endif
                </x-card>
            @endif
        </section>
    </div>
</div>
