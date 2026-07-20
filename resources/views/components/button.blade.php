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
    $variantClasses = [
        'primary' => 'bg-smartrest-success text-white shadow-sm hover:bg-green-700 focus-visible:outline-smartrest-success',
        'outline-primary' => 'border border-smartrest-success/40 bg-white text-green-800 hover:bg-smartrest-success/10 focus-visible:outline-smartrest-success',
        'secondary' => 'bg-slate-800 text-white shadow-sm hover:bg-slate-700 focus-visible:outline-slate-700',
        'outline-secondary' => 'border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 focus-visible:outline-slate-500',
        'danger' => 'bg-smartrest-danger text-white shadow-sm hover:bg-red-700 focus-visible:outline-smartrest-danger',
        'outline-danger' => 'border border-smartrest-danger/30 bg-white text-red-700 hover:bg-red-50 focus-visible:outline-smartrest-danger',
    ];

    $sizeClasses = [
        'sm' => 'px-3 py-1.5 text-xs',
        'lg' => 'px-5 py-3 text-base',
    ];

    $classes = [
        'inline-flex items-center justify-center rounded-sr-brand font-semibold transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 disabled:pointer-events-none disabled:opacity-60',
        $sizeClasses[$size] ?? 'px-4 py-2 text-sm',
        $variantClasses[$variant] ?? $variantClasses['primary'],
    ];
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
