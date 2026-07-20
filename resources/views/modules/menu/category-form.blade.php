<?php

declare(strict_types=1);

use App\Modules\Menu\Infrastructure\Models\MenuCategory;

/** @var MenuCategory|null $category */

$isEdit = $category instanceof MenuCategory;
$title = $isEdit ? __('menu.categories.edit_title') : __('menu.categories.create_title');
?>

@extends('layouts.admin')

@section('title', $title)

@section('content')
    <div class="row justify-content-center">
        <div class="col-12 col-lg-8">
            <section class="sr-card card">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start mb-4">
                        <div>
                            <p class="text-uppercase text-muted small mb-1">{{ __('menu.categories.heading') }}</p>
                            <h1 class="h4 mb-0">{{ $title }}</h1>
                        </div>
                        <a href="{{ route('admin.menu.index') }}" class="btn btn-outline-secondary btn-sm">
                            {{ __('menu.actions.back') }}
                        </a>
                    </div>

                    <form method="post" action="{{ $isEdit ? route('admin.menu.categories.update', ['category' => (int) $category->id]) : route('admin.menu.categories.store') }}" novalidate>
                        @csrf
                        @if ($isEdit)
                            @method('put')
                        @endif

                        @include('modules.menu.partials.localized-name-fields', ['model' => $category])

                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label for="sort_order" class="form-label">{{ __('menu.fields.sort_order') }}</label>
                                <input id="sort_order" name="sort_order" type="number" min="0" class="form-control @error('sort_order') is-invalid @enderror" value="{{ old('sort_order', $category?->sort_order ?? 0) }}" required>
                                @error('sort_order')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-12 col-md-6 d-flex align-items-end">
                                <div class="form-check mb-2">
                                    <input id="active" name="active" type="checkbox" value="1" class="form-check-input" @checked(old('active', $category?->active ?? true))>
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
