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

<div {{ $attributes->class(['form-check']) }}>
    <input type="hidden" name="{{ $name }}" value="0">
    <input
        id="{{ $fieldId }}"
        name="{{ $name }}"
        type="checkbox"
        value="1"
        class="form-check-input @error($name) is-invalid @enderror"
        @checked(old($name, $checked))
    >
    <label for="{{ $fieldId }}" class="form-check-label">{{ $label }}</label>
    @error($name)
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
