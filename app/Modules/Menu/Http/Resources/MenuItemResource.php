<?php

declare(strict_types=1);

namespace App\Modules\Menu\Http\Resources;

use App\Modules\Menu\Infrastructure\Models\MenuItem;

final class MenuItemResource
{
    /**
     * @param  iterable<MenuItem>  $items
     * @return list<array{id: int, category_id: int, name: string, price_minor: int, currency: string, active: bool, sort_order: int}>
     */
    public static function collection(iterable $items, string $locale): array
    {
        $data = [];

        foreach ($items as $item) {
            $data[] = self::make($item, $locale);
        }

        return $data;
    }

    /**
     * @return array{id: int, category_id: int, name: string, price_minor: int, currency: string, active: bool, sort_order: int}
     */
    public static function make(MenuItem $item, string $locale): array
    {
        return [
            'id' => (int) $item->id,
            'category_id' => (int) $item->category_id,
            'name' => $item->translatedName()->forLocale($locale),
            'price_minor' => (int) $item->price_minor,
            'currency' => (string) $item->currency,
            'active' => (bool) $item->active,
            'sort_order' => (int) $item->sort_order,
        ];
    }
}
