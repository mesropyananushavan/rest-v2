<?php

declare(strict_types=1);

use App\Modules\Menu\Infrastructure\Models\MenuCategory;

/** @var MenuCategory|null $category */
/** @var string $parentOptionsEndpoint */
/** @var list<array{id: int, label: string}> $parentInitialOptions */
/** @var int $selectedParentValue */
/** @var array{id: int, label: string}|null $selectedParentOption */

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

    <div class="mx-auto max-w-3xl">
        <x-card>
            <form method="post" action="{{ $isEdit ? route('admin.menu.categories.update', ['category' => (int) $category->id]) : route('admin.menu.categories.store') }}" novalidate>
                @csrf
                @if ($isEdit)
                    @method('put')
                @endif

                @include('modules.menu.partials.localized-name-fields', ['model' => $category])

                <x-form.searchable-select
                    name="parent_id"
                    :label="__('menu.fields.parent_category')"
                    :endpoint="$parentOptionsEndpoint"
                    :value="$selectedParentValue"
                    :selected="$selectedParentOption"
                    :initial-options="$parentInitialOptions"
                    :static-option="['id' => 0, 'label' => __('menu.categories.root_parent_option')]"
                    :placeholder="__('menu.searchable_select.parent_placeholder')"
                />

                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <x-form.input
                            name="sort_order"
                            type="number"
                            :label="__('menu.fields.sort_order')"
                            :value="$category?->sort_order ?? 0"
                            required
                        />
                    </div>
                    <div class="flex items-end pb-4">
                        <x-form.toggle
                            name="active"
                            :label="__('menu.fields.active')"
                            :checked="$category?->active ?? true"
                        />
                    </div>
                </div>

                <div class="mt-2 flex flex-col gap-2 sm:flex-row">
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
