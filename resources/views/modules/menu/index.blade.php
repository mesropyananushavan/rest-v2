<?php

declare(strict_types=1);

use App\Modules\Menu\Infrastructure\Models\MenuCategory;
use App\Modules\Menu\Infrastructure\Models\MenuItem;

/** @var \Illuminate\Database\Eloquent\Collection<int, MenuCategory> $categories */
/** @var \Illuminate\Database\Eloquent\Collection<int, MenuItem> $items */

$locale = app()->getLocale();
?>

@extends('layouts.admin')

@section('title', __('menu.index.title'))

@section('content')
    <div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-4">
        <div>
            <p class="text-uppercase text-muted small mb-1">{{ __('menu.index.eyebrow') }}</p>
            <h1 class="h3 mb-1">{{ __('menu.index.heading') }}</h1>
            <p class="text-muted mb-0">{{ __('menu.index.subtitle') }}</p>
        </div>
        <div class="d-flex gap-2 align-items-start">
            <a href="{{ route('admin.menu.categories.create') }}" class="btn btn-outline-primary">
                {{ __('menu.actions.create_category') }}
            </a>
            <a href="{{ route('admin.menu.items.create') }}" class="btn btn-primary">
                {{ __('menu.actions.create_item') }}
            </a>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-12 col-xl-4">
            <section class="sr-card card">
                <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                    <h2 class="h5 mb-0">{{ __('menu.categories.heading') }}</h2>
                    <span class="badge text-bg-light">{{ $categories->count() }}</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table sr-dense-table mb-0 align-middle">
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
                                            <span class="badge {{ $category->active ? 'text-bg-success' : 'text-bg-secondary' }}">
                                                {{ $category->active ? __('menu.status.active') : __('menu.status.inactive') }}
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <a href="{{ route('admin.menu.categories.edit', ['category' => (int) $category->id]) }}" class="btn btn-sm btn-outline-secondary">
                                                {{ __('menu.actions.edit') }}
                                            </a>
                                            <form method="post" action="{{ route('admin.menu.categories.destroy', ['category' => (int) $category->id]) }}" class="d-inline">
                                                @csrf
                                                @method('delete')
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    {{ __('menu.actions.delete') }}
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-muted text-center py-4">{{ __('menu.empty.categories') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>

        <div class="col-12 col-xl-8">
            <section class="sr-card card">
                <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                    <h2 class="h5 mb-0">{{ __('menu.items.heading') }}</h2>
                    <span class="badge text-bg-light">{{ $items->count() }}</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table sr-dense-table mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th>{{ __('menu.fields.name') }}</th>
                                    <th>{{ __('menu.fields.category') }}</th>
                                    <th>{{ __('menu.fields.price_minor') }}</th>
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
                                        <td>{{ $item->price()->minor }} {{ $item->price()->currency }}</td>
                                        <td>
                                            <span class="badge {{ $item->active ? 'text-bg-success' : 'text-bg-secondary' }}">
                                                {{ $item->active ? __('menu.status.active') : __('menu.status.inactive') }}
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <a href="{{ route('admin.menu.items.edit', ['item' => (int) $item->id]) }}" class="btn btn-sm btn-outline-secondary">
                                                {{ __('menu.actions.edit') }}
                                            </a>
                                            <form method="post" action="{{ route('admin.menu.items.destroy', ['item' => (int) $item->id]) }}" class="d-inline">
                                                @csrf
                                                @method('delete')
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    {{ __('menu.actions.delete') }}
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-muted text-center py-4">{{ __('menu.empty.items') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>
    </div>
@endsection
