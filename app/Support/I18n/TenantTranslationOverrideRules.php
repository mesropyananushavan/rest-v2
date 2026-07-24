<?php

declare(strict_types=1);

namespace App\Support\I18n;

final class TenantTranslationOverrideRules
{
    public const int MAX_VALUE_LENGTH = 1000;

    /**
     * @return list<string>
     */
    public static function supportedLocales(): array
    {
        return ['hy', 'ru', 'en'];
    }

    public static function isSupportedLocale(string $locale): bool
    {
        return in_array($locale, self::supportedLocales(), true);
    }
}
