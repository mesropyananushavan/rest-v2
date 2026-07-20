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
    <x-page-header
        :eyebrow="__('menu.categories.heading')"
        :title="$title"
    >
        <x-slot:actions>
            <x-button :href="route('admin.menu.index')" variant="outline-secondary" size="sm">
                {{ __('menu.actions.back') }}
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="row justify-content-center">
        <div class="col-12 col-lg-8">
            <x-card>
                <form method="post" action="{{ $isEdit ? route('admin.menu.categories.update', ['category' => (int) $category->id]) : route('admin.menu.categories.store') }}" novalidate>
                    @csrf
                    @if ($isEdit)
                        @method('put')
                    @endif

                    @include('modules.menu.partials.localized-name-fields', ['model' => $category])

                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <x-form.input
                                name="sort_order"
                                type="number"
                                :label="__('menu.fields.sort_order')"
                                :value="$category?->sort_order ?? 0"
                                required
                            />
                        </div>
                        <div class="col-12 col-md-6 d-flex align-items-end">
                            <x-form.toggle
                                class="mb-3"
                                name="active"
                                :label="__('menu.fields.active')"
                                :checked="$category?->active ?? true"
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
