<?php

declare(strict_types=1);

use App\Modules\Menu\Infrastructure\Models\MenuCategory;
use App\Modules\Menu\Infrastructure\Models\MenuItem;

/** @var MenuCategory|MenuItem|null $model */
?>

<div class="row g-3 mb-3">
    @foreach (['hy', 'ru', 'en'] as $locale)
        <div class="col-12 col-lg-4">
            <x-form.input
                name="name_{{ $locale }}"
                :label="__('menu.fields.name_'.$locale)"
                :value="$model?->translatedName()->forLocale($locale, $locale) ?? ''"
                required
            />
        </div>
    @endforeach
</div>
