<?php

declare(strict_types=1);

/** @var string $id */
/** @var string $action */
/** @var string $title */
/** @var string $message */
/** @var string $confirmLabel */
/** @var string $cancelLabel */
/** @var string $triggerLabel */
/** @var string $method */
?>

@props([
    'id',
    'action',
    'title' => __('admin.components.confirm_delete.title'),
    'message' => __('admin.components.confirm_delete.message'),
    'confirmLabel' => __('admin.actions.delete'),
    'cancelLabel' => __('admin.actions.cancel'),
    'triggerLabel' => __('admin.actions.delete'),
    'method' => 'delete',
])

<div x-data="{ open: false }" class="inline-flex" @keydown.escape.window="open = false">
    <button
        type="button"
        {{ $attributes->class(['inline-flex items-center justify-center rounded-sr-brand border border-smartrest-danger/30 bg-white px-3 py-1.5 text-xs font-semibold text-red-700 transition hover:bg-red-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-smartrest-danger']) }}
        @click="open = true"
    >
        {{ $triggerLabel }}
    </button>

    <div
        x-cloak
        x-show="open"
        x-transition.opacity
        id="{{ $id }}"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        role="dialog"
        aria-modal="true"
        aria-labelledby="{{ $id }}_title"
    >
        <button type="button" class="absolute inset-0 bg-slate-950/55" aria-label="{{ $cancelLabel }}" @click="open = false"></button>

        <div
            x-show="open"
            x-transition
            class="relative w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-black/10"
        >
            <div class="flex items-start justify-between gap-3 border-b border-slate-100 px-5 py-4">
                <h2 class="text-lg font-semibold text-smartrest-ink" id="{{ $id }}_title">{{ $title }}</h2>
                <button type="button" class="rounded-full px-2 py-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700" aria-label="{{ $cancelLabel }}" @click="open = false">
                    &times;
                </button>
            </div>
            <div class="px-5 py-4">
                <p class="text-sm text-slate-600">{{ $message }}</p>
            </div>
            <div class="flex flex-col-reverse gap-2 bg-slate-50 px-5 py-4 sm:flex-row sm:justify-end">
                <button type="button" class="inline-flex items-center justify-center rounded-sr-brand border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50" @click="open = false">
                    {{ $cancelLabel }}
                </button>
                <form method="post" action="{{ $action }}">
                    @csrf
                    @method($method)
                    <button type="submit" class="inline-flex w-full items-center justify-center rounded-sr-brand bg-smartrest-danger px-4 py-2 text-sm font-semibold text-white transition hover:bg-red-700 sm:w-auto">
                        {{ $confirmLabel }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
