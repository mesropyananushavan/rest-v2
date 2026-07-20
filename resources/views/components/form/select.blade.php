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

<div {{ $attributes->class(['mb-4']) }}>
    <label for="{{ $fieldId }}" class="mb-1.5 block text-sm font-semibold text-slate-700">{{ $label }}</label>
    <select
        id="{{ $fieldId }}"
        name="{{ $name }}"
        class="block w-full rounded-sr-control border bg-white px-3 py-2 text-sm text-smartrest-text shadow-sm outline-none transition focus:border-smartrest-success focus:ring-4 focus:ring-smartrest-success/10 @error($name) border-smartrest-danger focus:border-smartrest-danger focus:ring-smartrest-danger/10 @else border-slate-200 @enderror"
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
        <div class="mt-1.5 text-sm text-red-700">{{ $message }}</div>
    @enderror
</div>
