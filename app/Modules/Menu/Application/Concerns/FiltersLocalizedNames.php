<?php

declare(strict_types=1);

namespace App\Modules\Menu\Application\Concerns;

use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

trait FiltersLocalizedNames
{
    /**
     * @param  Builder<*>  $query
     */
    private function filterLocalizedName(Builder $query, string $column, ?string $search): void
    {
        $normalizedSearch = $this->normalizedSearch($search);

        if ($normalizedSearch === null) {
            return;
        }

        $driver = $query->getModel()->getConnection()->getDriverName();
        $needle = $driver === 'sqlite'
            ? $normalizedSearch
            : mb_strtolower($normalizedSearch, 'UTF-8');
        $escapeClause = $driver === 'pgsql' ? " ESCAPE E'\\\\'" : " ESCAPE '\\'";

        $query->whereRaw(
            $this->localizedNameSearchExpression($query, $column).' LIKE ?'.$escapeClause,
            ['%'.$this->escapeLike($needle).'%'],
        );
    }

    /**
     * @param  Builder<*>  $query
     */
    /**
     * @param  Builder<*>  $query
     * @return literal-string
     */
    private function localizedNameOrderExpression(Builder $query, string $column, string $locale): string
    {
        $fallbackLocale = 'en';

        if (! in_array($locale, ['hy', 'ru', 'en'], true) || $column !== 'translated_name') {
            throw new InvalidArgumentException('Unsupported localized menu order expression.');
        }

        return $this->localizedNameExpression(
            $query,
            [
                'pgsql' => [
                    'menu_categories' => "lower(coalesce(translated_name->>'hy', '') || ' ' || coalesce(translated_name->>'ru', '') || ' ' || coalesce(translated_name->>'en', ''))",
                    'menu_items' => "lower(coalesce(translated_name->>'hy', '') || ' ' || coalesce(translated_name->>'ru', '') || ' ' || coalesce(translated_name->>'en', ''))",
                ],
                'sqlite' => [
                    'menu_categories' => "lower(coalesce(json_extract(menu_categories.translated_name, '$.{$locale}'), json_extract(menu_categories.translated_name, '$.{$fallbackLocale}'), ''))",
                    'menu_items' => "lower(coalesce(json_extract(menu_items.translated_name, '$.{$locale}'), json_extract(menu_items.translated_name, '$.{$fallbackLocale}'), ''))",
                ],
                'default' => [
                    'menu_categories' => "lower(coalesce(json_unquote(json_extract(menu_categories.translated_name, '$.{$locale}')), json_unquote(json_extract(menu_categories.translated_name, '$.{$fallbackLocale}')), ''))",
                    'menu_items' => "lower(coalesce(json_unquote(json_extract(menu_items.translated_name, '$.{$locale}')), json_unquote(json_extract(menu_items.translated_name, '$.{$fallbackLocale}')), ''))",
                ],
            ],
        );
    }

    /**
     * @param  Builder<*>  $query
     * @return literal-string
     */
    private function localizedNameSearchExpression(Builder $query, string $column): string
    {
        if ($column !== 'translated_name') {
            throw new InvalidArgumentException('Unsupported localized menu search expression.');
        }

        return $this->localizedNameExpression(
            $query,
            [
                'pgsql' => [
                    'menu_categories' => "lower(coalesce(translated_name->>'hy', '') || ' ' || coalesce(translated_name->>'ru', '') || ' ' || coalesce(translated_name->>'en', ''))",
                    'menu_items' => "lower(coalesce(translated_name->>'hy', '') || ' ' || coalesce(translated_name->>'ru', '') || ' ' || coalesce(translated_name->>'en', ''))",
                ],
                'sqlite' => [
                    'menu_categories' => "lower(coalesce(json_extract(menu_categories.translated_name, '$.hy'), '') || ' ' || coalesce(json_extract(menu_categories.translated_name, '$.ru'), '') || ' ' || coalesce(json_extract(menu_categories.translated_name, '$.en'), ''))",
                    'menu_items' => "lower(coalesce(json_extract(menu_items.translated_name, '$.hy'), '') || ' ' || coalesce(json_extract(menu_items.translated_name, '$.ru'), '') || ' ' || coalesce(json_extract(menu_items.translated_name, '$.en'), ''))",
                ],
                'default' => [
                    'menu_categories' => "lower(concat(coalesce(json_unquote(json_extract(menu_categories.translated_name, '$.hy')), ''), ' ', coalesce(json_unquote(json_extract(menu_categories.translated_name, '$.ru')), ''), ' ', coalesce(json_unquote(json_extract(menu_categories.translated_name, '$.en')), '')))",
                    'menu_items' => "lower(concat(coalesce(json_unquote(json_extract(menu_items.translated_name, '$.hy')), ''), ' ', coalesce(json_unquote(json_extract(menu_items.translated_name, '$.ru')), ''), ' ', coalesce(json_unquote(json_extract(menu_items.translated_name, '$.en')), '')))",
                ],
            ],
        );
    }

    /**
     * @param  Builder<*>  $query
     * @param  array{pgsql: array{menu_categories: literal-string, menu_items: literal-string}, sqlite: array{menu_categories: literal-string, menu_items: literal-string}, default: array{menu_categories: literal-string, menu_items: literal-string}}  $expressions
     * @return literal-string
     */
    private function localizedNameExpression(Builder $query, array $expressions): string
    {
        $table = $query->getModel()->getTable();

        if (! in_array($table, ['menu_categories', 'menu_items'], true)) {
            throw new InvalidArgumentException('Unsupported localized menu table.');
        }

        $driver = $query->getModel()->getConnection()->getDriverName();

        return $expressions[$driver][$table] ?? $expressions['default'][$table];
    }

    private function normalizedSearch(?string $search): ?string
    {
        if ($search === null) {
            return null;
        }

        $search = trim($search);

        return $search === '' ? null : $search;
    }

    private function escapeLike(string $search): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
    }
}
