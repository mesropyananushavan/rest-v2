<?php

declare(strict_types=1);

namespace App\Support\I18n;

use Illuminate\Contracts\Translation\Loader;
use Illuminate\Support\Arr;
use Illuminate\Translation\Translator;

final class TenantAwareTranslator extends Translator
{
    public function __construct(
        Loader $loader,
        string $locale,
        private readonly TenantTranslationOverrides $overrides,
        private readonly TenantTranslationLocaleFallbacks $fallbacks,
        private readonly NonOverridableTranslationKeys $nonOverridableKeys,
    ) {
        parent::__construct($loader, $locale);
    }

    /**
     * @param  string  $key
     * @param  array<string, mixed>  $replace
     * @param  string|null  $locale
     * @param  bool  $fallback
     * @return string|array<array-key, mixed>
     */
    public function get($key, array $replace = [], $locale = null, $fallback = true)
    {
        $activeLocale = $locale ?? $this->locale;
        $resolved = $this->resolveLine($key, $activeLocale, (bool) $fallback);

        if ($resolved !== null) {
            return $this->replaceLine($resolved['line'], $replace);
        }

        $missingKey = $this->handleMissingTranslationKey($key, $replace, $activeLocale, (bool) $fallback);

        return $this->makeReplacements($missingKey, $replace);
    }

    /**
     * @param  string  $key
     * @param  \Countable|int|float|array<array-key, mixed>  $number
     * @param  array<string, mixed>  $replace
     * @param  string|null  $locale
     */
    public function choice($key, $number, array $replace = [], $locale = null): string
    {
        $activeLocale = $locale ?? $this->locale;
        $resolved = $this->resolveLine($key, $activeLocale, true);
        $line = $resolved['line'] ?? $key;
        $lineLocale = $resolved['locale'] ?? $activeLocale;

        if (! is_string($line)) {
            $line = $key;
            $lineLocale = $activeLocale;
        }

        if (is_countable($number)) {
            $number = count($number);
        }

        if (! isset($replace['count'])) {
            $replace['count'] = $number;
        }

        $choice = $this->getSelector()->choose($line, $number, $lineLocale);

        if (! is_string($choice)) {
            $choice = $line;
        }

        return $this->makeReplacements($choice, $replace);
    }

    /**
     * @param  string|null  $locale
     */
    public function has($key, $locale = null, $fallback = true): bool
    {
        $activeLocale = $locale ?? $this->locale;

        return $this->resolveLine($key, $activeLocale, (bool) $fallback) !== null;
    }

    /**
     * @param  string|null  $locale
     */
    public function hasForLocale($key, $locale = null): bool
    {
        $activeLocale = $locale ?? $this->locale;

        return $this->resolveLine($key, $activeLocale, false) !== null;
    }

    /**
     * @return array{line: string|array<array-key, mixed>, locale: string}|null
     */
    private function resolveLine(string $key, string $activeLocale, bool $fallback): ?array
    {
        $allowOverride = ! $this->nonOverridableKeys->contains($key);
        [$namespace, $group, $item] = $this->parsedTranslationKey($key);

        $activeLine = $this->resolveLineForLocale($key, $namespace, $group, $item, $activeLocale, $allowOverride);

        if ($activeLine !== null || ! $fallback) {
            return $activeLine;
        }

        $tenantDefaultLocale = $this->fallbacks->currentTenantDefaultLocale();

        if ($tenantDefaultLocale !== null && $tenantDefaultLocale !== $activeLocale) {
            $tenantDefaultLine = $this->resolveLineForLocale($key, $namespace, $group, $item, $tenantDefaultLocale, $allowOverride);

            if ($tenantDefaultLine !== null) {
                return $tenantDefaultLine;
            }
        }

        if ($activeLocale !== 'en' && $tenantDefaultLocale !== 'en') {
            return $this->resolveLineForLocale($key, $namespace, $group, $item, 'en', false);
        }

        return null;
    }

    /**
     * @return array{line: string|array<array-key, mixed>, locale: string}|null
     */
    private function resolveLineForLocale(
        string $key,
        string $namespace,
        string $group,
        ?string $item,
        string $locale,
        bool $allowOverride,
    ): ?array {
        if ($allowOverride) {
            $override = $this->overrides->get($locale, $key);

            if ($override !== null) {
                return ['line' => $override, 'locale' => $locale];
            }
        }

        $jsonLine = $this->jsonLine($locale, $key);

        if ($jsonLine !== null) {
            return ['line' => $jsonLine, 'locale' => $locale];
        }

        $fileLine = $this->fileLine($namespace, $group, $locale, $item);

        if ($fileLine !== null) {
            return ['line' => $fileLine, 'locale' => $locale];
        }

        return null;
    }

    /**
     * @return string|array<array-key, mixed>|null
     */
    private function jsonLine(string $locale, string $key): string|array|null
    {
        $this->load('*', '*', $locale);

        /** @var array<string, array<string, array<string, array<string, mixed>>>> $loaded */
        $loaded = $this->loaded;
        $line = $loaded['*']['*'][$locale][$key] ?? null;

        if (is_string($line) || is_array($line)) {
            return $line;
        }

        return null;
    }

    /**
     * @return string|array<array-key, mixed>|null
     */
    private function fileLine(string $namespace, string $group, string $locale, ?string $item): string|array|null
    {
        if ($item === null) {
            return null;
        }

        $this->load($namespace, $group, $locale);

        /** @var array<string, array<string, array<string, array<string, mixed>>>> $loaded */
        $loaded = $this->loaded;
        $lines = $loaded[$namespace][$group][$locale] ?? [];
        $line = Arr::get($lines, $item);

        if (is_string($line) || is_array($line)) {
            return $line;
        }

        return null;
    }

    /**
     * @param  string|array<array-key, mixed>  $line
     * @param  array<string, mixed>  $replace
     * @return string|array<array-key, mixed>
     */
    private function replaceLine(string|array $line, array $replace): string|array
    {
        if (is_string($line)) {
            return $this->makeReplacements($line, $replace);
        }

        if ($replace === []) {
            return $line;
        }

        array_walk_recursive($line, function (&$value) use ($replace): void {
            if (is_string($value)) {
                $value = $this->makeReplacements($value, $replace);
            }
        });

        return $line;
    }

    /**
     * @return array{0: string, 1: string, 2: string|null}
     */
    private function parsedTranslationKey(string $key): array
    {
        [$namespace, $group, $item] = $this->parseKey($key);

        return [
            is_string($namespace) ? $namespace : '*',
            is_string($group) ? $group : $key,
            is_string($item) ? $item : null,
        ];
    }
}
