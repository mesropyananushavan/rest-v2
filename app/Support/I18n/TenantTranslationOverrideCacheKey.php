<?php

declare(strict_types=1);

namespace App\Support\I18n;

final class TenantTranslationOverrideCacheKey
{
    public static function forTenantLocale(int $tenantId, string $locale): string
    {
        return "tenant:{$tenantId}:translation_overrides:{$locale}:v1";
    }

    public static function localesForTenant(int $tenantId): string
    {
        return "tenant:{$tenantId}:translation_overrides:locales:v1";
    }
}
