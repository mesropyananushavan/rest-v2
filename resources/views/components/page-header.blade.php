<?php

declare(strict_types=1);

/** @var string|null $eyebrow */
/** @var string $title */
/** @var string|null $subtitle */
?>

@props([
    'eyebrow' => null,
    'title',
    'subtitle' => null,
])

<div {{ $attributes->class(['mb-6 flex flex-col gap-4 rounded-sr-panel border border-black/5 bg-white/75 p-5 shadow-sm backdrop-blur-sr md:flex-row md:items-center md:justify-between']) }}>
    <div>
        @if ($eyebrow !== null)
            <p class="mb-2 text-xs font-bold uppercase tracking-[0.18em] text-smartrest-muted">{{ $eyebrow }}</p>
        @endif
        <h1 class="mb-2 text-3xl font-semibold tracking-tight text-smartrest-ink md:text-4xl">{{ $title }}</h1>
        @if ($subtitle !== null)
            <p class="max-w-2xl text-sm leading-6 text-smartrest-muted">{{ $subtitle }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="flex flex-wrap gap-2">
            {{ $actions }}
        </div>
    @endisset
</div>
