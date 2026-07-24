<?php

declare(strict_types=1);

/** @var string $menuContextUrl */
?>

<form wire:submit="save" novalidate>
    <x-form.searchable-select
        name="category_id"
        wire-model="category_id"
        :label="__('menu.fields.category')"
        :endpoint="$categoryOptionsEndpoint"
        :value="$category_id"
        :selected="$selectedCategoryOption"
        :initial-options="$categoryInitialOptions"
        :placeholder="__('menu.placeholders.select_category')"
        required
    />

    <div class="mb-4 grid gap-3 lg:grid-cols-3">
        @foreach (['hy', 'ru', 'en'] as $locale)
            <div>
                <label for="name_{{ $locale }}" class="mb-1.5 block text-sm font-semibold text-slate-700">{{ __('menu.fields.name_'.$locale) }}</label>
                <input
                    id="name_{{ $locale }}"
                    type="text"
                    wire:model="name_{{ $locale }}"
                    class="block w-full rounded-sr-control border bg-white px-3 py-2 text-sm text-smartrest-text shadow-sm outline-none transition placeholder:text-slate-400 focus:border-smartrest-success focus:ring-4 focus:ring-smartrest-success/10 @error('name_'.$locale) border-smartrest-danger focus:border-smartrest-danger focus:ring-smartrest-danger/10 @else border-slate-200 @enderror"
                    required
                >
                @error('name_'.$locale)
                    <div class="mt-1.5 text-sm text-red-700">{{ $message }}</div>
                @enderror
            </div>
        @endforeach
    </div>

    <div class="mb-4 grid gap-3 lg:grid-cols-3">
        @foreach (['hy', 'ru', 'en'] as $locale)
            <div>
                <label for="description_{{ $locale }}" class="mb-1.5 block text-sm font-semibold text-slate-700">{{ __('menu.fields.description_'.$locale) }}</label>
                <textarea
                    id="description_{{ $locale }}"
                    wire:model="description_{{ $locale }}"
                    rows="3"
                    class="block w-full rounded-sr-control border bg-white px-3 py-2 text-sm text-smartrest-text shadow-sm outline-none transition focus:border-smartrest-success focus:ring-4 focus:ring-smartrest-success/10 @error('description_'.$locale) border-smartrest-danger focus:border-smartrest-danger focus:ring-smartrest-danger/10 @else border-slate-200 @enderror"
                ></textarea>
                @error('description_'.$locale)
                    <div class="mt-1.5 text-sm text-red-700">{{ $message }}</div>
                @enderror
            </div>
        @endforeach
    </div>

    <div class="mb-6 grid gap-3 md:grid-cols-3">
        <div>
            <label for="price_major" class="mb-1.5 block text-sm font-semibold text-slate-700">{{ __('menu.fields.price_major') }}</label>
            <input id="price_major" type="text" wire:model="price_major" class="block w-full rounded-sr-control border bg-white px-3 py-2 text-sm text-smartrest-text shadow-sm outline-none transition focus:border-smartrest-success focus:ring-4 focus:ring-smartrest-success/10 @error('price_major') border-smartrest-danger focus:border-smartrest-danger focus:ring-smartrest-danger/10 @else border-slate-200 @enderror" required>
            @error('price_major')
                <div class="mt-1.5 text-sm text-red-700">{{ $message }}</div>
            @enderror
        </div>
        <div>
            <label for="currency" class="mb-1.5 block text-sm font-semibold text-slate-700">{{ __('menu.fields.currency') }}</label>
            <input id="currency" type="text" wire:model="currency" class="block w-full rounded-sr-control border bg-white px-3 py-2 text-sm text-smartrest-text shadow-sm outline-none transition focus:border-smartrest-success focus:ring-4 focus:ring-smartrest-success/10 @error('currency') border-smartrest-danger focus:border-smartrest-danger focus:ring-smartrest-danger/10 @else border-slate-200 @enderror" required>
            @error('currency')
                <div class="mt-1.5 text-sm text-red-700">{{ $message }}</div>
            @enderror
        </div>
        <div>
            <label for="sort_order" class="mb-1.5 block text-sm font-semibold text-slate-700">{{ __('menu.fields.sort_order') }}</label>
            <input id="sort_order" type="number" wire:model="sort_order" class="block w-full rounded-sr-control border bg-white px-3 py-2 text-sm text-smartrest-text shadow-sm outline-none transition focus:border-smartrest-success focus:ring-4 focus:ring-smartrest-success/10 @error('sort_order') border-smartrest-danger focus:border-smartrest-danger focus:ring-smartrest-danger/10 @else border-slate-200 @enderror" required>
            @error('sort_order')
                <div class="mt-1.5 text-sm text-red-700">{{ $message }}</div>
            @enderror
        </div>
        <div class="md:col-span-3">
            <label class="inline-flex items-center gap-3 text-sm font-semibold text-slate-700">
                <input type="checkbox" wire:model="active" class="h-5 w-5 rounded border-slate-300 text-smartrest-success focus:ring-smartrest-success/20">
                {{ __('menu.fields.active') }}
            </label>
            @error('active')
                <div class="mt-1.5 text-sm text-red-700">{{ $message }}</div>
            @enderror
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <div class="rounded-sr-card border border-slate-200 bg-slate-50 p-4">
            <div class="flex gap-4">
                <img src="{{ $this->internalPreviewUrl() }}" alt="{{ __('menu.images.internal_preview_alt') }}" class="h-24 w-24 rounded-2xl border border-slate-200 bg-white object-cover shadow-sm">
                <div class="min-w-0 flex-1">
                    <div class="text-sm font-bold text-smartrest-ink">{{ __('menu.images.internal_title') }}</div>
                    <p class="mt-1 text-sm leading-5 text-smartrest-muted">{{ __('menu.images.internal_help') }}</p>
                    <label for="internalUpload" class="mt-3 inline-flex cursor-pointer items-center justify-center rounded-sr-control border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:-translate-y-0.5 hover:border-smartrest-success hover:text-smartrest-success">
                        {{ $this->hasInternalImage() ? __('menu.actions.replace_image') : __('menu.actions.upload_image') }}
                    </label>
                    <input id="internalUpload" type="file" wire:model="internalUpload" accept="image/jpeg,image/png,image/webp" class="sr-only">
                    @if ($this->hasInternalImage())
                        <button type="button" wire:click="removeInternalImage" class="mt-3 inline-flex items-center justify-center rounded-sr-control border border-red-200 bg-white px-3 py-2 text-sm font-semibold text-red-700 shadow-sm transition hover:-translate-y-0.5 hover:bg-red-50">
                            {{ __('menu.actions.remove_image') }}
                        </button>
                    @endif
                </div>
            </div>
            @error('internalUpload')
                <div class="mt-2 text-sm text-red-700">{{ $message }}</div>
            @enderror
        </div>

        <div class="rounded-sr-card border border-slate-200 bg-slate-50 p-4">
            <div class="flex gap-4">
                <img src="{{ $this->publicPreviewUrl() }}" alt="{{ __('menu.images.public_preview_alt') }}" class="h-24 w-24 rounded-2xl border border-slate-200 bg-white object-cover shadow-sm">
                <div class="min-w-0 flex-1">
                    <div class="text-sm font-bold text-smartrest-ink">{{ __('menu.images.public_title') }}</div>
                    <p class="mt-1 text-sm leading-5 text-smartrest-muted">{{ __('menu.images.public_help') }}</p>
                    <label for="publicUpload" class="mt-3 inline-flex cursor-pointer items-center justify-center rounded-sr-control border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:-translate-y-0.5 hover:border-smartrest-success hover:text-smartrest-success">
                        {{ $this->hasPublicImage() ? __('menu.actions.replace_image') : __('menu.actions.upload_image') }}
                    </label>
                    <input id="publicUpload" type="file" wire:model="publicUpload" accept="image/jpeg,image/png,image/webp" class="sr-only">
                    @if ($this->hasPublicImage())
                        <button type="button" wire:click="removePublicImage" class="mt-3 inline-flex items-center justify-center rounded-sr-control border border-red-200 bg-white px-3 py-2 text-sm font-semibold text-red-700 shadow-sm transition hover:-translate-y-0.5 hover:bg-red-50">
                            {{ __('menu.actions.remove_image') }}
                        </button>
                    @endif
                </div>
            </div>
            @error('publicUpload')
                <div class="mt-2 text-sm text-red-700">{{ $message }}</div>
            @enderror
        </div>
    </div>

    <div class="mt-6 flex flex-col gap-2 sm:flex-row">
        <x-button type="submit" wire:loading.attr="disabled">
            {{ $isEdit ? __('menu.actions.save') : __('menu.actions.create') }}
        </x-button>
        <x-button :href="$menuContextUrl" variant="outline-secondary">
            {{ __('menu.actions.cancel') }}
        </x-button>
    </div>
</form>
