<?php

declare(strict_types=1);

/** @var string|null $href */
/** @var string $variant */
/** @var string|null $size */
/** @var string $type */
?>

@props([
    'href' => null,
    'variant' => 'primary',
    'size' => null,
    'type' => 'button',
])

@php
    $classes = ['btn', 'btn-'.$variant];

    if ($size !== null) {
        $classes[] = 'btn-'.$size;
    }
@endphp

@if ($href !== null)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}>
        {{ $slot }}
    </button>
@endif
