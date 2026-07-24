<?php

declare(strict_types=1);

use App\Support\I18n\Application\TenantTranslationOverrideRow;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Js;

/** @var list<string> $locales */
/** @var int $maxValueLength */
/** @var LengthAwarePaginator<int, TenantTranslationOverrideRow> $rows */
?>

<div>
    @if ($statusMessage !== null)
        <div class="mb-4 rounded-sr-brand border border-smartrest-success/20 bg-smartrest-success/10 px-4 py-3 text-sm font-medium text-green-800" role="status">
            {{ $statusMessage }}
        </div>
    @endif

    @if ($errorMessage !== null)
        <div class="mb-4 rounded-sr-brand border border-smartrest-danger/20 bg-red-50 px-4 py-3 text-sm font-medium text-red-800" role="alert">
            {{ $errorMessage }}
        </div>
    @endif

    <x-page-header
        :eyebrow="__('admin.translation_overrides.eyebrow')"
        :title="__('admin.translation_overrides.heading')"
        :subtitle="__('admin.translation_overrides.subtitle')"
    />

    <x-card body-class="p-4" class="mb-4">
        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_12rem]">
            <div>
                <label for="translation_override_search" class="mb-1.5 block text-sm font-semibold text-slate-700">{{ __('admin.translation_overrides.search.label') }}</label>
                <input
                    id="translation_override_search"
                    type="search"
                    wire:model.live.debounce.350ms="search"
                    class="block w-full rounded-sr-control border border-slate-200 bg-white px-3 py-2 text-sm text-smartrest-text shadow-sm outline-none transition placeholder:text-slate-400 focus:border-smartrest-success focus:ring-4 focus:ring-smartrest-success/10"
                    placeholder="{{ __('admin.translation_overrides.search.placeholder') }}"
                >
                <p class="mt-1.5 text-sm text-smartrest-muted">{{ __('admin.translation_overrides.search.help') }}</p>
            </div>

            <div>
                <label for="translation_override_locale" class="mb-1.5 block text-sm font-semibold text-slate-700">{{ __('admin.translation_overrides.locale.label') }}</label>
                <select
                    id="translation_override_locale"
                    wire:model.live="locale"
                    class="block w-full rounded-sr-control border border-slate-200 bg-white px-3 py-2 text-sm text-smartrest-text shadow-sm outline-none transition focus:border-smartrest-success focus:ring-4 focus:ring-smartrest-success/10"
                >
                    @foreach ($locales as $availableLocale)
                        <option value="{{ $availableLocale }}">{{ __('admin.locales.'.$availableLocale) }}</option>
                    @endforeach
                </select>
                <p class="mt-1.5 text-sm text-smartrest-muted">{{ __('admin.translation_overrides.locale.help') }}</p>
            </div>
        </div>

        @if ($search !== '')
            <x-button type="button" variant="outline-secondary" size="sm" class="mt-3" wire:click="clearSearch">
                {{ __('admin.translation_overrides.actions.clear_search') }}
            </x-button>
        @endif
    </x-card>

    <x-card :title="__('admin.translation_overrides.results.heading')" :count="$rows->total()" body-class="p-0">
        @if ($rows->count() > 0)
            <x-table>
                <thead>
                    <tr>
                        <th>{{ __('admin.translation_overrides.table.effective_value') }}</th>
                        <th>{{ __('admin.translation_overrides.table.key') }}</th>
                        <th>{{ __('admin.translation_overrides.table.status') }}</th>
                        <th>{{ __('admin.translation_overrides.table.locale_values') }}</th>
                        <th class="text-right">{{ __('admin.translation_overrides.table.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($rows as $row)
                        <tr wire:key="translation-override-row-{{ sha1($row->key) }}">
                            <td class="min-w-64">
                                <div class="max-w-xl whitespace-normal text-sm font-semibold text-smartrest-ink">{{ $row->effectiveValue }}</div>
                                @if ($editingKey === $row->key)
                                    <form wire:submit="save" class="mt-3 rounded-2xl border border-emerald-100 bg-emerald-50/60 p-3">
                                        <label for="translation_override_value_{{ sha1($row->key) }}" class="mb-1.5 block text-sm font-semibold text-green-900">{{ __('admin.translation_overrides.edit.value_label') }}</label>
                                        <textarea
                                            id="translation_override_value_{{ sha1($row->key) }}"
                                            wire:model="overrideValue"
                                            maxlength="{{ $maxValueLength }}"
                                            rows="3"
                                            class="block w-full rounded-sr-control border border-emerald-200 bg-white px-3 py-2 text-sm text-smartrest-text shadow-sm outline-none transition focus:border-smartrest-success focus:ring-4 focus:ring-smartrest-success/10"
                                        ></textarea>
                                        @error('overrideValue')
                                            <div class="mt-1.5 text-sm text-red-700">{{ $message }}</div>
                                        @enderror
                                        <p class="mt-1.5 text-xs text-green-900/70">{{ __('admin.translation_overrides.edit.default_value', ['value' => $row->languageValues[$locale] ?? '']) }}</p>
                                        <div class="mt-3 flex flex-wrap gap-2">
                                            <x-button type="submit" size="sm">
                                                {{ __('admin.translation_overrides.actions.save') }}
                                            </x-button>
                                            <x-button type="button" variant="outline-secondary" size="sm" wire:click="cancelEditing">
                                                {{ __('admin.actions.cancel') }}
                                            </x-button>
                                        </div>
                                    </form>
                                @endif
                            </td>
                            <td>
                                <code class="rounded-lg bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700">{{ $row->key }}</code>
                            </td>
                            <td>
                                @if ($row->overridden)
                                    <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-bold text-green-800">{{ __('admin.translation_overrides.status.overridden') }}</span>
                                @else
                                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-600">{{ __('admin.translation_overrides.status.default') }}</span>
                                @endif
                            </td>
                            <td class="min-w-72">
                                <div class="grid gap-2">
                                    @foreach ($locales as $availableLocale)
                                        <div class="rounded-xl border border-slate-100 bg-slate-50 px-3 py-2">
                                            <div class="mb-1 text-[0.68rem] font-black uppercase tracking-[0.16em] text-slate-500">{{ __('admin.locales.'.$availableLocale) }}</div>
                                            <div class="whitespace-normal text-sm text-smartrest-ink">{{ $row->values[$availableLocale] ?? '' }}</div>
                                        </div>
                                    @endforeach
                                </div>
                            </td>
                            <td>
                                <div class="flex justify-end gap-2">
                                    <x-button type="button" variant="outline-primary" size="sm" wire:click="startEditing({{ Js::from($row->key) }})">
                                        {{ __('admin.translation_overrides.actions.edit') }}
                                    </x-button>
                                    @if ($row->overridden)
                                        <x-button type="button" variant="outline-danger" size="sm" wire:click="resetOverride({{ Js::from($row->key) }})">
                                            {{ __('admin.translation_overrides.actions.reset') }}
                                        </x-button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-table>

            @if ($rows->hasPages())
                <div class="flex items-center justify-between gap-2 border-t border-slate-100 p-3 text-sm text-smartrest-muted">
                    <span>{{ __('admin.translation_overrides.pagination.page_of', ['page' => $rows->currentPage(), 'pages' => $rows->lastPage()]) }}</span>
                    <div class="flex gap-2">
                        <x-button type="button" variant="outline-secondary" size="sm" wire:click="previousPage" :disabled="$rows->onFirstPage()">
                            {{ __('admin.translation_overrides.pagination.previous') }}
                        </x-button>
                        <x-button type="button" variant="outline-secondary" size="sm" wire:click="nextPage" :disabled="! $rows->hasMorePages()">
                            {{ __('admin.translation_overrides.pagination.next') }}
                        </x-button>
                    </div>
                </div>
            @endif
        @else
            <div class="p-6 text-center">
                <div class="text-base font-black text-smartrest-ink">{{ __('admin.translation_overrides.empty.title') }}</div>
                <p class="mt-1 text-sm text-smartrest-muted">{{ __('admin.translation_overrides.empty.body') }}</p>
            </div>
        @endif
    </x-card>
</div>
