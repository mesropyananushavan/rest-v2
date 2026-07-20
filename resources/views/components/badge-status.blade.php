<?php

declare(strict_types=1);

/** @var bool $active */
/** @var string $activeLabel */
/** @var string $inactiveLabel */
?>

@props([
    'active',
    'activeLabel',
    'inactiveLabel',
])

<span {{ $attributes->class([
    'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset',
    $active ? 'bg-smartrest-success/10 text-green-800 ring-smartrest-success/25' : 'bg-slate-100 text-slate-600 ring-slate-200',
]) }}>
    {{ $active ? $activeLabel : $inactiveLabel }}
</span>
