<?php

declare(strict_types=1);

use App\Modules\Menu\Infrastructure\Models\MenuCategory;
use App\Modules\Menu\Infrastructure\Models\MenuItem;

/** @var MenuCategory|MenuItem|null $model */
?>

<div class="row g-3 mb-3">
    @foreach (['hy', 'ru', 'en'] as $locale)
        <div class="col-12 col-lg-4">
            <label for="name_{{ $locale }}" class="form-label">{{ __('menu.fields.name_'.$locale) }}</label>
            <input
                id="name_{{ $locale }}"
                name="name_{{ $locale }}"
                type="text"
                class="form-control @error('name_'.$locale) is-invalid @enderror"
                value="{{ old('name_'.$locale, $model?->translatedName()->forLocale($locale, $locale) ?? '') }}"
                required
            >
            @error('name_'.$locale)
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    @endforeach
</div>
