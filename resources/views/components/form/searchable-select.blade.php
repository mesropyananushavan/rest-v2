@props([
    'name',
    'label',
    'endpoint',
    'placeholder' => null,
    'required' => false,
    'wireModel' => null,
    'value' => null,
    'selected' => null,
    'initialOptions' => [],
    'staticOption' => null,
])

<?php

/**
 * @var array{id: int, label: string}|null $selected
 * @var list<array{id: int, label: string}> $initialOptions
 * @var array{id: int, label: string}|null $staticOption
 */

$fieldId = $attributes->get('id') ?? $name;
$listboxId = $fieldId.'_listbox';
$errorKey = str_replace(['[', ']'], ['.', ''], $name);
$selectedValue = old($name, $value ?? $selected['id'] ?? $staticOption['id'] ?? '');
?>

<div
    x-data="smartrestSearchableSelect({
        endpoint: @js($endpoint),
        initialOptions: @js($initialOptions),
        selected: @js($selected),
        staticOption: @js($staticOption),
        placeholder: @js($placeholder ?? __('menu.searchable_select.placeholder')),
    })"
    class="mb-4"
    @click.outside="close()"
>
    <label for="{{ $fieldId }}" class="mb-1.5 block text-sm font-semibold text-slate-700">{{ $label }}</label>

    <input
        x-ref="hidden"
        type="hidden"
        name="{{ $name }}"
        value="{{ $selectedValue }}"
        @if ($wireModel !== null) wire:model="{{ $wireModel }}" @endif
    >

    <div class="relative">
        <input
            id="{{ $fieldId }}"
            type="text"
            x-ref="search"
            x-model="query"
            role="combobox"
            aria-autocomplete="list"
            aria-controls="{{ $listboxId }}"
            :aria-expanded="open.toString()"
            :aria-activedescendant="activeDescendantId(@js($fieldId))"
            autocomplete="off"
            class="block w-full rounded-sr-control border bg-white px-3 py-2 pr-16 text-sm text-smartrest-text shadow-sm outline-none transition placeholder:text-slate-400 focus:border-smartrest-success focus:ring-4 focus:ring-smartrest-success/10 @error($errorKey) border-smartrest-danger focus:border-smartrest-danger focus:ring-smartrest-danger/10 @else border-slate-200 @enderror"
            :placeholder="placeholder"
            @focus="openList()"
            @input.debounce.250ms="search()"
            @keydown.arrow-down.prevent="highlightNext()"
            @keydown.arrow-up.prevent="highlightPrevious()"
            @keydown.enter.prevent="chooseHighlighted()"
            @keydown.escape.prevent="close()"
            @if ($required) required @endif
        >

        <button
            type="button"
            x-show="selected !== null"
            class="absolute right-9 top-1/2 inline-flex h-7 w-7 -translate-y-1/2 items-center justify-center rounded-full text-slate-500 transition hover:bg-slate-100 hover:text-slate-700"
            :aria-label="@js(__('menu.searchable_select.clear'))"
            @click="clear()"
        >
            <span aria-hidden="true">×</span>
        </button>

        <button
            type="button"
            class="absolute right-2 top-1/2 inline-flex h-7 w-7 -translate-y-1/2 items-center justify-center rounded-full text-slate-500 transition hover:bg-slate-100 hover:text-slate-700"
            :aria-label="@js(__('menu.searchable_select.open'))"
            @click="toggle()"
        >
            <span aria-hidden="true">⌄</span>
        </button>

        <div
            x-cloak
            x-show="open"
            class="absolute z-30 mt-1 max-h-64 w-full overflow-auto rounded-sr-card border border-slate-200 bg-white py-1 text-sm shadow-lg"
        >
            <ul id="{{ $listboxId }}" role="listbox">
                <template x-for="(option, index) in options" :key="option.id">
                    <li
                        :id="@js($fieldId.'_option_') + index"
                        role="option"
                        :aria-selected="index === highlightedIndex"
                    >
                        <button
                            type="button"
                            class="block w-full px-3 py-2 text-left transition"
                            :class="index === highlightedIndex ? 'bg-smartrest-success/10 text-smartrest-success' : 'text-smartrest-text hover:bg-slate-50'"
                            @mouseenter="highlightedIndex = index"
                            @click="choose(option)"
                        >
                            <span x-text="option.label"></span>
                        </button>
                    </li>
                </template>
            </ul>

            <div x-show="loading" class="px-3 py-2 text-sm text-slate-500">{{ __('menu.searchable_select.loading') }}</div>
            <div x-show="! loading && options.length === 0" class="px-3 py-2 text-sm text-slate-500">{{ __('menu.searchable_select.no_results') }}</div>

            <button
                x-show="! loading && hasMore"
                type="button"
                class="block w-full border-t border-slate-100 px-3 py-2 text-left text-sm font-semibold text-smartrest-success transition hover:bg-smartrest-success/5"
                @click="loadMore()"
            >
                {{ __('menu.searchable_select.load_more') }}
            </button>
        </div>
    </div>

    @error($errorKey)
        <div class="mt-1.5 text-sm text-red-700">{{ $message }}</div>
    @enderror
</div>
