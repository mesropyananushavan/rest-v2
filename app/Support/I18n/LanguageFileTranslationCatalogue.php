<?php

declare(strict_types=1);

namespace App\Support\I18n;

use Illuminate\Contracts\Translation\Loader;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;

final class LanguageFileTranslationCatalogue
{
    private const string CACHE_VERSION = 'v1';

    public function __construct(
        private readonly Loader $loader,
        private readonly Filesystem $files,
        private readonly string $langPath,
    ) {}

    /**
     * @return array<string, string>
     */
    public function forLocale(string $locale): array
    {
        if (! TenantTranslationOverrideRules::isSupportedLocale($locale)) {
            return [];
        }

        /** @var array<string, string> $catalogue */
        $catalogue = Cache::rememberForever(
            $this->cacheKeyForLocale($locale),
            fn (): array => $this->loadLocale($locale),
        );

        return $catalogue;
    }

    public function cacheKeyForLocale(string $locale): string
    {
        return sprintf(
            'app:language_file_translation_catalogue:%s:%s:%s',
            $locale,
            $this->fingerprintForLocale($locale),
            self::CACHE_VERSION,
        );
    }

    /**
     * @return array<string, string>
     */
    private function loadLocale(string $locale): array
    {
        $catalogue = [];

        foreach ($this->groupNamesForLocale($locale) as $group) {
            /** @var array<string, mixed> $lines */
            $lines = $this->loader->load($locale, $group, '*');
            $catalogue += $this->flatten($lines, $group);
        }

        ksort($catalogue);

        return $catalogue;
    }

    /**
     * @return list<string>
     */
    private function groupNamesForLocale(string $locale): array
    {
        $directory = rtrim($this->langPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$locale;
        $files = $this->files->glob($directory.DIRECTORY_SEPARATOR.'*.php') ?: [];

        $groups = [];

        foreach ($files as $file) {
            if (is_string($file)) {
                $groups[] = pathinfo($file, PATHINFO_FILENAME);
            }
        }

        sort($groups);

        return $groups;
    }

    /**
     * @param  array<string, mixed>  $lines
     * @return array<string, string>
     */
    private function flatten(array $lines, string $prefix): array
    {
        $flattened = [];

        foreach ($lines as $key => $value) {
            $translationKey = "{$prefix}.{$key}";

            if (is_string($value)) {
                $flattened[$translationKey] = $value;

                continue;
            }

            if (is_array($value)) {
                /** @var array<string, mixed> $nested */
                $nested = $value;
                $flattened += $this->flatten($nested, $translationKey);
            }
        }

        return $flattened;
    }

    private function fingerprintForLocale(string $locale): string
    {
        $directory = rtrim($this->langPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$locale;
        $files = $this->files->glob($directory.DIRECTORY_SEPARATOR.'*.php') ?: [];
        sort($files);

        $metadata = [];

        foreach ($files as $file) {
            if (! is_string($file) || ! $this->files->isFile($file)) {
                continue;
            }

            $metadata[] = [
                'name' => basename($file),
                'mtime' => $this->files->lastModified($file),
                'size' => $this->files->size($file),
            ];
        }

        return substr(sha1((string) json_encode($metadata)), 0, 16);
    }
}
