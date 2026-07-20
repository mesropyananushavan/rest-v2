<?php

declare(strict_types=1);

use App\Modules\Menu\Infrastructure\Models\MenuCategory;
use App\Modules\Menu\Infrastructure\Models\MenuItem;

/** @var MenuCategory|MenuItem|null $model */
?>

<div class="mb-4 grid gap-3 lg:grid-cols-3">
    @foreach (['hy', 'ru', 'en'] as $locale)
        <div>
            <x-form.input
                name="name_{{ $locale }}"
                :label="__('menu.fields.name_'.$locale)"
                :value="$model?->translatedName()->forLocale($locale, $locale) ?? ''"
                required
            />
        </div>
    @endforeach
</div>
