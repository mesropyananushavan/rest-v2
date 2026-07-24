<?php

declare(strict_types=1);

/** @var string $id */
/** @var string $label */
?>

@props([
    'id',
    'label',
])

<div
    x-data="{
        id: @js($id),
        open: false,
        toggle() {
            if (this.open) {
                this.close(false);

                return;
            }

            this.$dispatch('row-overflow-opened', { id: this.id });
            this.open = true;
            this.$nextTick(() => this.focusFirstItem());
        },
        close(focusTrigger) {
            this.open = false;

            if (focusTrigger) {
                this.$nextTick(() => this.$refs.trigger.focus());
            }
        },
        focusFirstItem() {
            const item = this.$refs.menu?.querySelector('a, button');

            if (item !== null && item !== undefined) {
                item.focus();
            }
        },
    }"
    class="relative inline-flex"
    @row-overflow-opened.window="if ($event.detail.id !== id) open = false"
    @keydown.escape.prevent.stop="if (open) close(true)"
    @click.outside="if (open) close(false)"
>
    <button
        type="button"
        x-ref="trigger"
        class="inline-flex h-9 w-9 items-center justify-center rounded-sr-brand border border-slate-200 bg-white text-lg font-black leading-none text-slate-600 shadow-sm transition hover:bg-slate-50 hover:text-smartrest-ink focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-500"
        aria-haspopup="menu"
        aria-controls="{{ $id }}_menu"
        :aria-expanded="open.toString()"
        @click="toggle()"
    >
        <span class="sr-only">{{ $label }}</span>
        <span aria-hidden="true">...</span>
    </button>

    <div
        x-cloak
        x-show="open"
        x-transition.origin.top.right
        x-ref="menu"
        id="{{ $id }}_menu"
        role="menu"
        tabindex="-1"
        class="absolute right-0 top-full z-30 mt-2 min-w-44 overflow-hidden rounded-sr-card border border-slate-200 bg-white py-1 text-sm shadow-lg ring-1 ring-black/5"
    >
        {{ $slot }}
    </div>
</div>
