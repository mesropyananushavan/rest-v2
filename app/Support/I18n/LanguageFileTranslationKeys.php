<?php

declare(strict_types=1);

namespace App\Support\I18n;

use Illuminate\Contracts\Translation\Loader;
use Illuminate\Support\Arr;
use Illuminate\Support\NamespacedItemResolver;

final class LanguageFileTranslationKeys
{
    private readonly NamespacedItemResolver $resolver;

    public function __construct(
        private readonly Loader $loader,
    ) {
        $this->resolver = new NamespacedItemResolver;
    }

    public function exists(string $key): bool
    {
        foreach (TenantTranslationOverrideRules::supportedLocales() as $locale) {
            if (is_string($this->lineForLocale($key, $locale))) {
                return true;
            }
        }

        return false;
    }

    private function lineForLocale(string $key, string $locale): mixed
    {
        [$namespace, $group, $item] = $this->resolver->parseKey($key);

        if (! is_string($group) || ! is_string($item)) {
            return null;
        }

        $lines = $this->loader->load($locale, $group, is_string($namespace) ? $namespace : '*');

        return Arr::get($lines, $item);
    }
}
