<?php

declare(strict_types=1);

namespace App\Support\I18n\Application;

use App\Support\I18n\LanguageFileTranslationCatalogue;
use App\Support\I18n\NonOverridableTranslationKeys;
use App\Support\I18n\TenantTranslationOverrideRules;
use App\Support\I18n\TenantTranslationOverrides;
use Illuminate\Pagination\LengthAwarePaginator;

final class SearchTenantTranslationOverrides
{
    public function __construct(
        private readonly LanguageFileTranslationCatalogue $catalogue,
        private readonly TenantTranslationOverrides $overrides,
        private readonly NonOverridableTranslationKeys $nonOverridableKeys,
    ) {}

    /**
     * @return LengthAwarePaginator<int, TenantTranslationOverrideRow>
     */
    public function __invoke(string $locale, string $search, int $page, int $perPage): LengthAwarePaginator
    {
        $locale = TenantTranslationOverrideRules::isSupportedLocale($locale) ? $locale : 'hy';
        $page = max(1, $page);
        $perPage = max(1, $perPage);

        $catalogues = $this->cataloguesByLocale();
        $overrides = $this->overridesByLocale();
        $keys = $this->editableKeys($catalogues);
        $needle = $this->normalize($search);
        $rows = [];

        foreach ($keys as $key) {
            $languageValues = $this->valuesForKey($catalogues, $key);
            $effectiveValues = $this->effectiveValuesForKey($languageValues, $overrides, $key);
            $effectiveValue = $effectiveValues[$locale] ?? '';

            if ($needle !== '' && ! $this->matches($key, $effectiveValue, $needle)) {
                continue;
            }

            $rows[] = new TenantTranslationOverrideRow(
                key: $key,
                effectiveValue: $effectiveValue,
                overridden: array_key_exists($key, $overrides[$locale] ?? []),
                values: $effectiveValues,
                languageValues: $languageValues,
            );
        }

        $total = count($rows);
        $pageRows = array_slice($rows, ($page - 1) * $perPage, $perPage);

        return new LengthAwarePaginator($pageRows, $total, $perPage, $page, [
            'pageName' => 'page',
        ]);
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function cataloguesByLocale(): array
    {
        $catalogues = [];

        foreach (TenantTranslationOverrideRules::supportedLocales() as $locale) {
            $catalogues[$locale] = $this->catalogue->forLocale($locale);
        }

        return $catalogues;
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function overridesByLocale(): array
    {
        $overrides = [];

        foreach (TenantTranslationOverrideRules::supportedLocales() as $locale) {
            $overrides[$locale] = $this->overrides->overridesForLocale($locale);
        }

        return $overrides;
    }

    /**
     * @param  array<string, array<string, string>>  $catalogues
     * @return list<string>
     */
    private function editableKeys(array $catalogues): array
    {
        $keys = [];

        foreach ($catalogues as $catalogue) {
            foreach (array_keys($catalogue) as $key) {
                $keys[$key] = true;
            }
        }

        $editable = [];

        foreach (array_keys($keys) as $key) {
            if (! $this->nonOverridableKeys->contains($key)) {
                $editable[] = $key;
            }
        }

        sort($editable);

        return $editable;
    }

    /**
     * @param  array<string, array<string, string>>  $catalogues
     * @return array<string, string>
     */
    private function valuesForKey(array $catalogues, string $key): array
    {
        $values = [];

        foreach (TenantTranslationOverrideRules::supportedLocales() as $locale) {
            $values[$locale] = $catalogues[$locale][$key] ?? '';
        }

        return $values;
    }

    /**
     * @param  array<string, string>  $languageValues
     * @param  array<string, array<string, string>>  $overrides
     * @return array<string, string>
     */
    private function effectiveValuesForKey(array $languageValues, array $overrides, string $key): array
    {
        $values = [];

        foreach (TenantTranslationOverrideRules::supportedLocales() as $locale) {
            $values[$locale] = $overrides[$locale][$key] ?? $languageValues[$locale] ?? '';
        }

        return $values;
    }

    private function matches(string $key, string $effectiveValue, string $needle): bool
    {
        return str_contains($this->normalize($effectiveValue), $needle)
            || str_contains($this->normalize($key), $needle);
    }

    private function normalize(string $value): string
    {
        return mb_strtolower(trim($value));
    }
}
