<?php

declare(strict_types=1);

use App\Modules\Menu\Infrastructure\Models\MenuCategory;
use App\Modules\Menu\Infrastructure\Models\MenuItem;
use App\Support\Money\MoneyFormatter;

/** @var \Illuminate\Database\Eloquent\Collection<int, MenuCategory> $categories */
/** @var \Illuminate\Database\Eloquent\Collection<int, MenuItem> $items */
/** @var bool $canViewArchive */
/** @var bool $showArchived */

$locale = app()->getLocale();
$canManageCategories = auth()->user()?->can('menu.categories.manage') ?? false;
$canManageItems = auth()->user()?->can('menu.items.manage') ?? false;
?>

@extends('layouts.admin')

@section('title', __('menu.index.title'))

@section('content')
    <x-page-header
        :eyebrow="__('menu.index.eyebrow')"
        :title="__('menu.index.heading')"
        :subtitle="__('menu.index.subtitle')"
    >
        <x-slot:actions>
            @if ($canViewArchive)
                <x-button :href="route('admin.menu.index', ['show_archived' => $showArchived ? '0' : '1'])" variant="outline-secondary">
                    {{ $showArchived ? __('menu.actions.hide_archived') : __('menu.actions.show_archived') }}
                </x-button>
            @endif
            @if ($canManageCategories)
                <x-button :href="route('admin.menu.categories.create')" variant="outline-primary">
                    {{ __('menu.actions.create_category') }}
                </x-button>
            @endif
            @if ($canManageItems)
                <x-button :href="route('admin.menu.items.create')">
                    {{ __('menu.actions.create_item') }}
                </x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-4 xl:grid-cols-[minmax(0,0.9fr)_minmax(0,1.6fr)]">
        <div>
            <x-card :title="__('menu.categories.heading')" :count="$categories->count()" body-class="p-0">
                <x-table>
                    <thead>
                        <tr>
                            <th>{{ __('menu.fields.name') }}</th>
                            <th>{{ __('menu.fields.active') }}</th>
                            <th class="text-right">{{ __('menu.fields.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($categories as $category)
                            <tr>
                                <td>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="font-semibold text-smartrest-ink">{{ $category->translatedName()->forLocale($locale) }}</span>
                                        @if ($category->trashed())
                                            <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800 ring-1 ring-amber-200">{{ __('menu.status.archived') }}</span>
                                        @endif
                                    </div>
                                    <div class="text-sm text-smartrest-muted">{{ __('menu.fields.sort_order') }}: {{ $category->sort_order }}</div>
                                </td>
                                <td>
                                    <x-badge-status
                                        :active="$category->active"
                                        :active-label="__('menu.status.active')"
                                        :inactive-label="__('menu.status.inactive')"
                                    />
                                </td>
                                <td class="text-right">
                                    <div class="flex justify-end gap-2">
                                        @if (! $category->trashed())
                                            <x-button :href="route('admin.menu.categories.edit', ['category' => (int) $category->id])" variant="outline-secondary" size="sm">
                                                {{ __('menu.actions.edit') }}
                                            </x-button>
                                        @endif
                                        @if (! $category->trashed() && $canManageCategories)
                                            <x-confirm-modal
                                                id="archive_category_{{ (int) $category->id }}"
                                                :action="route('admin.menu.categories.destroy', ['category' => (int) $category->id])"
                                                :title="__('menu.confirm.archive_category_title')"
                                                :message="__('menu.confirm.archive_category_message')"
                                                :trigger-label="__('menu.actions.archive')"
                                                :confirm-label="__('menu.actions.archive')"
                                            />
                                        @endif
                                        @if ($category->trashed() && $canManageCategories && $canViewArchive)
                                            <form method="post" action="{{ route('admin.menu.categories.restore', ['category' => (int) $category->id]) }}">
                                                @csrf
                                                <x-button type="submit" variant="outline-primary" size="sm">
                                                    {{ __('menu.actions.restore') }}
                                                </x-button>
                                            </form>
                                            <x-confirm-modal
                                                id="force_delete_category_{{ (int) $category->id }}"
                                                :action="route('admin.menu.categories.force-delete', ['category' => (int) $category->id])"
                                                :title="__('menu.confirm.force_delete_category_title')"
                                                :message="__('menu.confirm.force_delete_category_message')"
                                                :trigger-label="__('menu.actions.force_delete')"
                                                :confirm-label="__('menu.actions.force_delete')"
                                            />
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="py-6 text-center text-sm text-smartrest-muted">{{ __('menu.empty.categories') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-table>
            </x-card>
        </div>

        <div>
            <x-card :title="__('menu.items.heading')" :count="$items->count()" body-class="p-0">
                <x-table>
                    <thead>
                        <tr>
                            <th>{{ __('menu.fields.name') }}</th>
                            <th>{{ __('menu.fields.category') }}</th>
                            <th>{{ __('menu.fields.price') }}</th>
                            <th>{{ __('menu.fields.active') }}</th>
                            <th class="text-right">{{ __('menu.fields.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($items as $item)
                            <tr>
                                <td>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="font-semibold text-smartrest-ink">{{ $item->translatedName()->forLocale($locale) }}</span>
                                        @if ($item->trashed())
                                            <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800 ring-1 ring-amber-200">{{ __('menu.status.archived') }}</span>
                                        @endif
                                    </div>
                                    @if ($item->translatedDescription() !== null)
                                        <div class="text-sm text-smartrest-muted">{{ $item->translatedDescription()?->forLocale($locale) }}</div>
                                    @endif
                                </td>
                                <td>{{ $item->category?->translatedName()->forLocale($locale) }}</td>
                                <td>{{ MoneyFormatter::format($item->price(), $locale) }}</td>
                                <td>
                                    <x-badge-status
                                        :active="$item->active"
                                        :active-label="__('menu.status.active')"
                                        :inactive-label="__('menu.status.inactive')"
                                    />
                                </td>
                                <td class="text-right">
                                    <div class="flex justify-end gap-2">
                                        @if (! $item->trashed())
                                            <x-button :href="route('admin.menu.items.edit', ['item' => (int) $item->id])" variant="outline-secondary" size="sm">
                                                {{ __('menu.actions.edit') }}
                                            </x-button>
                                        @endif
                                        @if (! $item->trashed() && $canManageItems)
                                            <x-confirm-modal
                                                id="archive_item_{{ (int) $item->id }}"
                                                :action="route('admin.menu.items.destroy', ['item' => (int) $item->id])"
                                                :title="__('menu.confirm.archive_item_title')"
                                                :message="__('menu.confirm.archive_item_message')"
                                                :trigger-label="__('menu.actions.archive')"
                                                :confirm-label="__('menu.actions.archive')"
                                            />
                                        @endif
                                        @if ($item->trashed() && $canManageItems && $canViewArchive && ! $item->category?->trashed())
                                            <form method="post" action="{{ route('admin.menu.items.restore', ['item' => (int) $item->id]) }}">
                                                @csrf
                                                <x-button type="submit" variant="outline-primary" size="sm">
                                                    {{ __('menu.actions.restore') }}
                                                </x-button>
                                            </form>
                                        @endif
                                        @if ($item->trashed() && $canManageItems && $canViewArchive)
                                            <x-confirm-modal
                                                id="force_delete_item_{{ (int) $item->id }}"
                                                :action="route('admin.menu.items.force-delete', ['item' => (int) $item->id])"
                                                :title="__('menu.confirm.force_delete_item_title')"
                                                :message="__('menu.confirm.force_delete_item_message')"
                                                :trigger-label="__('menu.actions.force_delete')"
                                                :confirm-label="__('menu.actions.force_delete')"
                                            />
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-6 text-center text-sm text-smartrest-muted">{{ __('menu.empty.items') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-table>
            </x-card>
        </div>
    </div>
@endsection
