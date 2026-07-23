<?php

declare(strict_types=1);

use App\Modules\Menu\Infrastructure\Models\MenuItem;

/** @var \Illuminate\Database\Eloquent\Collection<int, \App\Modules\Menu\Infrastructure\Models\MenuCategory> $categories */
/** @var string $defaultCurrency */
/** @var MenuItem|null $item */

$isEdit = $item instanceof MenuItem;
$title = $isEdit ? __('menu.items.edit_title') : __('menu.items.create_title');
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
            <livewire:admin.menu-item-form
                :default-currency="$defaultCurrency"
                :item="$item"
            />
        </x-card>
    </div>
@endsection
