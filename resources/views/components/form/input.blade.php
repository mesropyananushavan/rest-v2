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

<div {{ $attributes->class(['mb-3']) }}>
    <label for="{{ $fieldId }}" class="form-label">{{ $label }}</label>
    <input
        id="{{ $fieldId }}"
        name="{{ $name }}"
        type="{{ $type }}"
        value="{{ is_scalar($fieldValue) ? $fieldValue : '' }}"
        class="form-control @error($name) is-invalid @enderror"
        @required($required)
    >
    @error($name)
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
