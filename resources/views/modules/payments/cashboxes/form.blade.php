<?php

declare(strict_types=1);

use App\Modules\Payments\Infrastructure\Models\Cashbox;

/** @var Cashbox|null $cashbox */

$isEdit = $cashbox instanceof Cashbox;
$title = $isEdit ? __('payments.cashboxes.form.edit_title') : __('payments.cashboxes.form.create_title');
?>

@extends('layouts.admin')

@section('title', $title)

@section('content')
    <x-page-header
        :eyebrow="__('payments.cashboxes.index.eyebrow')"
        :title="$title"
    >
        <x-slot:actions>
            <x-button :href="route('admin.payments.cashboxes.index')" variant="outline-secondary" size="sm">
                {{ __('payments.cashboxes.actions.back') }}
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="mx-auto max-w-2xl">
        <x-card>
            <form method="post" action="{{ $isEdit ? route('admin.payments.cashboxes.update', ['cashbox' => (int) $cashbox->id]) : route('admin.payments.cashboxes.store') }}" novalidate>
                @csrf
                @if ($isEdit)
                    @method('put')
                @endif

                <x-form.input
                    name="name"
                    :label="__('payments.cashboxes.fields.name')"
                    :value="old('name', $cashbox?->name ?? '')"
                    required
                />

                <div class="mt-2 rounded-sr-brand border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-smartrest-muted">
                    {{ __('payments.cashboxes.form.lifecycle_help') }}
                </div>

                <div class="mt-5 flex flex-col gap-2 sm:flex-row">
                    <x-button type="submit">
                        {{ $isEdit ? __('payments.cashboxes.actions.save') : __('payments.cashboxes.actions.create') }}
                    </x-button>
                    <x-button :href="route('admin.payments.cashboxes.index')" variant="outline-secondary">
                        {{ __('payments.cashboxes.actions.cancel') }}
                    </x-button>
                </div>
            </form>
        </x-card>
    </div>
@endsection
