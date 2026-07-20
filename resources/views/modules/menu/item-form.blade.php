<?php

declare(strict_types=1);

use App\Modules\Menu\Infrastructure\Models\MenuItem;

/** @var \Illuminate\Database\Eloquent\Collection<int, \App\Modules\Menu\Infrastructure\Models\MenuCategory> $categories */
/** @var MenuItem|null $item */

$isEdit = $item instanceof MenuItem;
$title = $isEdit ? __('menu.items.edit_title') : __('menu.items.create_title');
?>

@extends('layouts.admin')

@section('title', $title)

@section('content')
    <div class="row justify-content-center">
        <div class="col-12 col-xl-9">
            <section class="sr-card card">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start mb-4">
                        <div>
                            <p class="text-uppercase text-muted small mb-1">{{ __('menu.items.heading') }}</p>
                            <h1 class="h4 mb-0">{{ $title }}</h1>
                        </div>
                        <a href="{{ route('admin.menu.index') }}" class="btn btn-outline-secondary btn-sm">
                            {{ __('menu.actions.back') }}
                        </a>
                    </div>

                    <form method="post" action="{{ $isEdit ? route('admin.menu.items.update', ['item' => (int) $item->id]) : route('admin.menu.items.store') }}" novalidate>
                        @csrf
                        @if ($isEdit)
                            @method('put')
                        @endif

                        <div class="mb-3">
                            <label for="category_id" class="form-label">{{ __('menu.fields.category') }}</label>
                            <select id="category_id" name="category_id" class="form-select @error('category_id') is-invalid @enderror" required>
                                <option value="">{{ __('menu.placeholders.select_category') }}</option>
                                @foreach ($categories as $category)
                                    <option value="{{ (int) $category->id }}" @selected((int) old('category_id', $item?->category_id ?? 0) === (int) $category->id)>
                                        {{ $category->translatedName()->forLocale(app()->getLocale()) }}
                                    </option>
                                @endforeach
                            </select>
                            @error('category_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

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
                                <label for="price_minor" class="form-label">{{ __('menu.fields.price_minor') }}</label>
                                <input id="price_minor" name="price_minor" type="number" min="0" class="form-control @error('price_minor') is-invalid @enderror" value="{{ old('price_minor', $item?->price_minor ?? 0) }}" required>
                                @error('price_minor')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-12 col-md-4">
                                <label for="currency" class="form-label">{{ __('menu.fields.currency') }}</label>
                                <input id="currency" name="currency" type="text" maxlength="3" class="form-control @error('currency') is-invalid @enderror" value="{{ old('currency', $item?->currency ?? 'AMD') }}" required>
                                @error('currency')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-12 col-md-4">
                                <label for="sort_order" class="form-label">{{ __('menu.fields.sort_order') }}</label>
                                <input id="sort_order" name="sort_order" type="number" min="0" class="form-control @error('sort_order') is-invalid @enderror" value="{{ old('sort_order', $item?->sort_order ?? 0) }}" required>
                                @error('sort_order')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-12">
                                <div class="form-check">
                                    <input id="active" name="active" type="checkbox" value="1" class="form-check-input" @checked(old('active', $item?->active ?? true))>
                                    <label for="active" class="form-check-label">{{ __('menu.fields.active') }}</label>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                {{ $isEdit ? __('menu.actions.save') : __('menu.actions.create') }}
                            </button>
                            <a href="{{ route('admin.menu.index') }}" class="btn btn-outline-secondary">
                                {{ __('menu.actions.cancel') }}
                            </a>
                        </div>
                    </form>
                </div>
            </section>
        </div>
    </div>
@endsection
