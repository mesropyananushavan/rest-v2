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

<div {{ $attributes->class(['sr-page-hero mb-4']) }}>
    <div>
        @if ($eyebrow !== null)
            <p class="text-uppercase text-muted small mb-2">{{ $eyebrow }}</p>
        @endif
        <h1 class="display-6 fw-semibold mb-2">{{ $title }}</h1>
        @if ($subtitle !== null)
            <p class="text-muted mb-0">{{ $subtitle }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="d-flex flex-wrap gap-2">
            {{ $actions }}
        </div>
    @endisset
</div>
