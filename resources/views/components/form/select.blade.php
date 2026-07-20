<?php

declare(strict_types=1);

/** @var string $name */
/** @var string $label */
/** @var string|null $id */
/** @var array<int|string, string> $options */
/** @var int|string|null $selected */
/** @var string|null $placeholder */
/** @var bool $required */
?>

@props([
    'name',
    'label',
    'id' => null,
    'options' => [],
    'selected' => null,
    'placeholder' => null,
    'required' => false,
])

@php
    $fieldId = $id ?? str_replace(['.', '[', ']'], '_', $name);
    $selectedValue = old($name, $selected);
@endphp

<div {{ $attributes->class(['mb-3']) }}>
    <label for="{{ $fieldId }}" class="form-label">{{ $label }}</label>
    <select
        id="{{ $fieldId }}"
        name="{{ $name }}"
        class="form-select @error($name) is-invalid @enderror"
        @required($required)
    >
        @if ($placeholder !== null)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected((string) $selectedValue === (string) $optionValue)>
                {{ $optionLabel }}
            </option>
        @endforeach
    </select>
    @error($name)
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
