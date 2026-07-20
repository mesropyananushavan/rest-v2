<?php

declare(strict_types=1);

use App\Modules\Menu\Infrastructure\Models\MenuCategory;
use App\Modules\Menu\Infrastructure\Models\MenuItem;
use App\Support\Money\MoneyFormatter;

/** @var \Illuminate\Database\Eloquent\Collection<int, MenuCategory> $categories */
/** @var \Illuminate\Database\Eloquent\Collection<int, MenuItem> $items */

$locale = app()->getLocale();
$canDelete = (bool) data_get(auth()->user(), 'is_superadmin');
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
            <x-button :href="route('admin.menu.categories.create')" variant="outline-primary">
                {{ __('menu.actions.create_category') }}
            </x-button>
            <x-button :href="route('admin.menu.items.create')">
                {{ __('menu.actions.create_item') }}
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="row g-4">
        <div class="col-12 col-xl-4">
            <x-card :title="__('menu.categories.heading')" :count="$categories->count()" body-class="p-0">
                <x-table>
                    <thead>
                        <tr>
                            <th>{{ __('menu.fields.name') }}</th>
                            <th>{{ __('menu.fields.active') }}</th>
                            <th class="text-end">{{ __('menu.fields.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($categories as $category)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $category->translatedName()->forLocale($locale) }}</div>
                                    <div class="text-muted small">{{ __('menu.fields.sort_order') }}: {{ $category->sort_order }}</div>
                                </td>
                                <td>
                                    <x-badge-status
                                        :active="$category->active"
                                        :active-label="__('menu.status.active')"
                                        :inactive-label="__('menu.status.inactive')"
                                    />
                                </td>
                                <td class="text-end">
                                    <x-button :href="route('admin.menu.categories.edit', ['category' => (int) $category->id])" variant="outline-secondary" size="sm">
                                        {{ __('menu.actions.edit') }}
                                    </x-button>
                                    @if ($canDelete)
                                        <x-confirm-modal
                                            id="delete_category_{{ (int) $category->id }}"
                                            :action="route('admin.menu.categories.destroy', ['category' => (int) $category->id])"
                                            :trigger-label="__('menu.actions.delete')"
                                            :confirm-label="__('menu.actions.delete')"
                                        />
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-muted text-center py-4">{{ __('menu.empty.categories') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-table>
            </x-card>
        </div>

        <div class="col-12 col-xl-8">
            <x-card :title="__('menu.items.heading')" :count="$items->count()" body-class="p-0">
                <x-table>
                    <thead>
                        <tr>
                            <th>{{ __('menu.fields.name') }}</th>
                            <th>{{ __('menu.fields.category') }}</th>
                            <th>{{ __('menu.fields.price') }}</th>
                            <th>{{ __('menu.fields.active') }}</th>
                            <th class="text-end">{{ __('menu.fields.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($items as $item)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $item->translatedName()->forLocale($locale) }}</div>
                                    @if ($item->translatedDescription() !== null)
                                        <div class="text-muted small">{{ $item->translatedDescription()?->forLocale($locale) }}</div>
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
                                <td class="text-end">
                                    <x-button :href="route('admin.menu.items.edit', ['item' => (int) $item->id])" variant="outline-secondary" size="sm">
                                        {{ __('menu.actions.edit') }}
                                    </x-button>
                                    @if ($canDelete)
                                        <x-confirm-modal
                                            id="delete_item_{{ (int) $item->id }}"
                                            :action="route('admin.menu.items.destroy', ['item' => (int) $item->id])"
                                            :trigger-label="__('menu.actions.delete')"
                                            :confirm-label="__('menu.actions.delete')"
                                        />
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-muted text-center py-4">{{ __('menu.empty.items') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-table>
            </x-card>
        </div>
    </div>
@endsection
