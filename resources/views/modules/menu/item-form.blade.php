<?php

declare(strict_types=1);

use App\Modules\Menu\Infrastructure\Models\MenuItem;

/** @var \Illuminate\Database\Eloquent\Collection<int, \App\Modules\Menu\Infrastructure\Models\MenuCategory> $categories */
/** @var MenuItem|null $item */

$isEdit = $item instanceof MenuItem;
$title = $isEdit ? __('menu.items.edit_title') : __('menu.items.create_title');
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

    <div class="row justify-content-center">
        <div class="col-12 col-xl-9">
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

                    <div class="row g-3 mb-3">
                        @foreach (['hy', 'ru', 'en'] as $locale)
                            <div class="col-12 col-lg-4">
                                <label for="description_{{ $locale }}" class="form-label">{{ __('menu.fields.description_'.$locale) }}</label>
                                <textarea id="description_{{ $locale }}" name="description_{{ $locale }}" rows="3" class="form-control @error('description_'.$locale) is-invalid @enderror">{{ old('description_'.$locale, $item?->translatedDescription()?->forLocale($locale, $locale) ?? '') }}</textarea>
                                @error('description_'.$locale)
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        @endforeach
                    </div>

                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <x-form.input
                                name="price_minor"
                                type="number"
                                :label="__('menu.fields.price_minor')"
                                :value="$item?->price_minor ?? 0"
                                required
                            />
                        </div>
                        <div class="col-12 col-md-4">
                            <x-form.input
                                name="currency"
                                :label="__('menu.fields.currency')"
                                :value="$item?->currency ?? 'AMD'"
                                required
                            />
                        </div>
                        <div class="col-12 col-md-4">
                            <x-form.input
                                name="sort_order"
                                type="number"
                                :label="__('menu.fields.sort_order')"
                                :value="$item?->sort_order ?? 0"
                                required
                            />
                        </div>
                        <div class="col-12">
                            <x-form.toggle
                                name="active"
                                :label="__('menu.fields.active')"
                                :checked="$item?->active ?? true"
                            />
                        </div>
                    </div>

                    <div class="mt-4 d-flex gap-2">
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
    </div>
@endsection
