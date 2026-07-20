<?php

declare(strict_types=1);

/** @var string $name */
/** @var string $label */
/** @var string|null $id */
/** @var bool $checked */
?>

@props([
    'name',
    'label',
    'id' => null,
    'checked' => false,
])

@php
    $fieldId = $id ?? str_replace(['.', '[', ']'], '_', $name);
@endphp

<div {{ $attributes->class(['flex items-center gap-3']) }}>
    <input type="hidden" name="{{ $name }}" value="0">
    <input
        id="{{ $fieldId }}"
        name="{{ $name }}"
        type="checkbox"
        value="1"
        class="h-5 w-5 rounded border-slate-300 text-smartrest-success focus:ring-smartrest-success/20 @error($name) border-smartrest-danger @enderror"
        @checked(old($name, $checked))
    >
    <label for="{{ $fieldId }}" class="text-sm font-semibold text-slate-700">{{ $label }}</label>
    @error($name)
        <div class="text-sm text-red-700">{{ $message }}</div>
    @enderror
</div>
