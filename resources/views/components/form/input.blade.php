<?php

declare(strict_types=1);

/** @var string $name */
/** @var string $label */
/** @var string $type */
/** @var string|null $id */
/** @var mixed $value */
/** @var bool $required */
?>

@props([
    'name',
    'label',
    'type' => 'text',
    'id' => null,
    'value' => null,
    'required' => false,
])

@php
    $fieldId = $id ?? str_replace(['.', '[', ']'], '_', $name);
    $fieldValue = old($name, $value);
@endphp

<div {{ $attributes->class(['mb-4']) }}>
    <label for="{{ $fieldId }}" class="mb-1.5 block text-sm font-semibold text-slate-700">{{ $label }}</label>
    <input
        id="{{ $fieldId }}"
        name="{{ $name }}"
        type="{{ $type }}"
        value="{{ is_scalar($fieldValue) ? $fieldValue : '' }}"
        class="block w-full rounded-sr-control border bg-white px-3 py-2 text-sm text-smartrest-text shadow-sm outline-none transition placeholder:text-slate-400 focus:border-smartrest-success focus:ring-4 focus:ring-smartrest-success/10 @error($name) border-smartrest-danger focus:border-smartrest-danger focus:ring-smartrest-danger/10 @else border-slate-200 @enderror"
        @required($required)
    >
    @error($name)
        <div class="mt-1.5 text-sm text-red-700">{{ $message }}</div>
    @enderror
</div>
