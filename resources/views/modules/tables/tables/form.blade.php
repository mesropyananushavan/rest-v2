<?php

declare(strict_types=1);

use App\Modules\Tables\Infrastructure\Models\Hall;
use App\Modules\Tables\Infrastructure\Models\Table;

/** @var Hall $hall */
/** @var Table|null $table */

$isEdit = $table instanceof Table;
$title = $isEdit ? __('tables.tables.form.edit_title') : __('tables.tables.form.create_title');
?>

@extends('layouts.admin')

@section('title', $title)

@section('content')
    <x-page-header
        :eyebrow="$hall->translatedName()->forLocale(app()->getLocale(), 'en')"
        :title="$title"
    >
        <x-slot:actions>
            <x-button :href="route('admin.tables.tables.index', ['hall' => (int) $hall->id])" variant="outline-secondary" size="sm">
                {{ __('tables.tables.actions.back') }}
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="mx-auto max-w-3xl">
        <x-card>
            <form method="post" action="{{ $isEdit ? route('admin.tables.tables.update', ['hall' => (int) $hall->id, 'table' => (int) $table->id]) : route('admin.tables.tables.store', ['hall' => (int) $hall->id]) }}" novalidate>
                @csrf
                @if ($isEdit)
                    @method('put')
                @endif

                <div class="mb-4 grid gap-3 lg:grid-cols-3">
                    @foreach (['hy', 'ru', 'en'] as $locale)
                        <x-form.input
                            name="name_{{ $locale }}"
                            :label="__('tables.tables.fields.name_'.$locale)"
                            :value="$table?->translatedName()->forLocale($locale, $locale) ?? ''"
                            required
                        />
                    @endforeach
                </div>

                <div class="grid gap-3 md:grid-cols-2">
                    <x-form.select
                        name="type"
                        :label="__('tables.tables.fields.type')"
                        :options="[
                            'standard' => __('tables.tables.types.standard'),
                            'vip' => __('tables.tables.types.vip'),
                        ]"
                        :selected="$table?->type ?? 'standard'"
                        required
                    />
                    <x-form.select
                        name="shape"
                        :label="__('tables.tables.fields.shape')"
                        :options="[
                            'circle' => __('tables.tables.shapes.circle'),
                            'square' => __('tables.tables.shapes.square'),
                            'rectangle' => __('tables.tables.shapes.rectangle'),
                        ]"
                        :selected="$table?->shape ?? 'square'"
                        required
                    />
                </div>

                <div class="grid gap-3 md:grid-cols-3">
                    <x-form.input
                        name="hdm_department"
                        type="number"
                        :label="__('tables.tables.fields.hdm_department')"
                        :value="$table?->hdm_department"
                    />
                    <x-form.input
                        name="sort_order"
                        type="number"
                        :label="__('tables.tables.fields.sort_order')"
                        :value="$table?->sort_order ?? 0"
                        required
                    />
                    <div class="flex items-end gap-4 pb-4">
                        <x-form.toggle
                            name="active"
                            :label="__('tables.tables.fields.active')"
                            :checked="$table?->active ?? true"
                        />
                        <x-form.toggle
                            name="is_delivery"
                            :label="__('tables.tables.fields.is_delivery')"
                            :checked="$table?->is_delivery ?? false"
                        />
                    </div>
                </div>

                <div class="mt-2 flex flex-col gap-2 sm:flex-row">
                    <x-button type="submit">
                        {{ $isEdit ? __('tables.tables.actions.save') : __('tables.tables.actions.create') }}
                    </x-button>
                    <x-button :href="route('admin.tables.tables.index', ['hall' => (int) $hall->id])" variant="outline-secondary">
                        {{ __('tables.tables.actions.cancel') }}
                    </x-button>
                </div>
            </form>
        </x-card>
    </div>
@endsection
