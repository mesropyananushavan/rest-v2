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

<span {{ $attributes->class(['badge', $active ? 'text-bg-success' : 'text-bg-secondary']) }}>
    {{ $active ? $activeLabel : $inactiveLabel }}
</span>
