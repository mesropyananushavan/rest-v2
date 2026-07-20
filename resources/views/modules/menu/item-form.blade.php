<?php

declare(strict_types=1);

use App\Modules\Menu\Infrastructure\Models\MenuItem;
use App\Support\Money\MoneyFormatter;

/** @var \Illuminate\Database\Eloquent\Collection<int, \App\Modules\Menu\Infrastructure\Models\MenuCategory> $categories */
/** @var string $defaultCurrency */
/** @var MenuItem|null $item */

$isEdit = $item instanceof MenuItem;
$title = $isEdit ? __('menu.items.edit_title') : __('menu.items.create_title');
$priceMajor = $isEdit ? MoneyFormatter::toMajor($item->price()) : '0';
$categoryOptions = $categories
    ->mapWithKeys(fn ($category): array => [
        (int) $category->id => $category->translatedName()->forLocale(app()->getLocale()),
    ])
    ->all();
?>

@extends('layouts.admin')

@section('title', $title)

@section('content')
    <x-page-header
        :eyebrow="__('menu.items.heading')"
        :title="$title"
    >
        <x-slot:actions>
            <x-button :href="route('admin.menu.index')" variant="outline-secondary" size="sm">
                {{ __('menu.actions.back') }}
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="mx-auto max-w-5xl">
        <x-card>
            <form method="post" action="{{ $isEdit ? route('admin.menu.items.update', ['item' => (int) $item->id]) : route('admin.menu.items.store') }}" novalidate>
                @csrf
                @if ($isEdit)
                    @method('put')
                @endif

                <x-form.select
                    name="category_id"
                    :label="__('menu.fields.category')"
                    :options="$categoryOptions"
                    :selected="$item?->category_id"
                    :placeholder="__('menu.placeholders.select_category')"
                    required
                />

                @include('modules.menu.partials.localized-name-fields', ['model' => $item])

                <div class="mb-4 grid gap-3 lg:grid-cols-3">
                    @foreach (['hy', 'ru', 'en'] as $locale)
                        <div>
                            <label for="description_{{ $locale }}" class="mb-1.5 block text-sm font-semibold text-slate-700">{{ __('menu.fields.description_'.$locale) }}</label>
                            <textarea id="description_{{ $locale }}" name="description_{{ $locale }}" rows="3" class="block w-full rounded-sr-control border bg-white px-3 py-2 text-sm text-smartrest-text shadow-sm outline-none transition focus:border-smartrest-success focus:ring-4 focus:ring-smartrest-success/10 @error('description_'.$locale) border-smartrest-danger focus:border-smartrest-danger focus:ring-smartrest-danger/10 @else border-slate-200 @enderror">{{ old('description_'.$locale, $item?->translatedDescription()?->forLocale($locale, $locale) ?? '') }}</textarea>
                            @error('description_'.$locale)
                                <div class="mt-1.5 text-sm text-red-700">{{ $message }}</div>
                            @enderror
                        </div>
                    @endforeach
                </div>

                <div class="grid gap-3 md:grid-cols-3">
                    <div>
                        <x-form.input
                            name="price_major"
                            :label="__('menu.fields.price_major')"
                            :value="$priceMajor"
                            required
                        />
                    </div>
                    <div>
                        <x-form.input
                            name="currency"
                            :label="__('menu.fields.currency')"
                            :value="$item?->currency ?? $defaultCurrency"
                            required
                        />
                    </div>
                    <div>
                        <x-form.input
                            name="sort_order"
                            type="number"
                            :label="__('menu.fields.sort_order')"
                            :value="$item?->sort_order ?? 0"
                            required
                        />
                    </div>
                    <div class="md:col-span-3">
                        <x-form.toggle
                            name="active"
                            :label="__('menu.fields.active')"
                            :checked="$item?->active ?? true"
                        />
                    </div>
                </div>

                <div class="mt-6 flex flex-col gap-2 sm:flex-row">
                    <x-button type="submit">
                        {{ $isEdit ? __('menu.actions.save') : __('menu.actions.create') }}
                    </x-button>
                    <x-button :href="route('admin.menu.index')" variant="outline-secondary">
                        {{ __('menu.actions.cancel') }}
                    </x-button>
                </div>
            </form>
        </x-card>
    </div>
@endsection
