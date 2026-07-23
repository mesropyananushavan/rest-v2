<?php

declare(strict_types=1);

use App\Modules\Tables\Infrastructure\Models\Hall;

/** @var Hall|null $hall */

$isEdit = $hall instanceof Hall;
$title = $isEdit ? __('tables.halls.form.edit_title') : __('tables.halls.form.create_title');
?>

@extends('layouts.admin')

@section('title', $title)

@section('content')
    <x-page-header
        :eyebrow="__('tables.halls.index.heading')"
        :title="$title"
    >
        <x-slot:actions>
            <x-button :href="route('admin.tables.halls.index')" variant="outline-secondary" size="sm">
                {{ __('tables.halls.actions.back') }}
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="mx-auto max-w-3xl">
        <x-card>
            <form method="post" action="{{ $isEdit ? route('admin.tables.halls.update', ['hall' => (int) $hall->id]) : route('admin.tables.halls.store') }}" novalidate>
                @csrf
                @if ($isEdit)
                    @method('put')
                @endif

                <div class="mb-4 grid gap-3 lg:grid-cols-3">
                    @foreach (['hy', 'ru', 'en'] as $locale)
                        <x-form.input
                            name="name_{{ $locale }}"
                            :label="__('tables.halls.fields.name_'.$locale)"
                            :value="$hall?->translatedName()->forLocale($locale, $locale) ?? ''"
                            required
                        />
                    @endforeach
                </div>

                <div class="grid gap-3 md:grid-cols-3">
                    <x-form.input
                        name="color"
                        type="color"
                        :label="__('tables.halls.fields.color')"
                        :value="$hall?->color ?? '#5FA8D3'"
                        required
                    />
                    <x-form.input
                        name="sort_order"
                        type="number"
                        :label="__('tables.halls.fields.sort_order')"
                        :value="$hall?->sort_order ?? 0"
                        required
                    />
                    <div class="flex items-end pb-4">
                        <x-form.toggle
                            name="active"
                            :label="__('tables.halls.fields.active')"
                            :checked="$hall?->active ?? true"
                        />
                    </div>
                </div>

                <div class="mt-2 flex flex-col gap-2 sm:flex-row">
                    <x-button type="submit">
                        {{ $isEdit ? __('tables.halls.actions.save') : __('tables.halls.actions.create') }}
                    </x-button>
                    <x-button :href="route('admin.tables.halls.index')" variant="outline-secondary">
                        {{ __('tables.halls.actions.cancel') }}
                    </x-button>
                </div>
            </form>
        </x-card>
    </div>
@endsection
