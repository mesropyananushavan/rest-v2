<?php

declare(strict_types=1);

namespace App\Support\I18n;

use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Modules\Tenancy\Contracts\TenantSettingsReader;

final class TenantTranslationLocaleFallbacks
{
    /**
     * @var array<int, string|null>
     */
    private array $tenantDefaultLocales = [];

    public function __construct(
        private readonly TenantResolver $tenants,
        private readonly TenantSettingsReader $settings,
    ) {}

    /**
     * @return list<string>
     */
    public function localesFor(string $activeLocale, bool $fallback): array
    {
        if (! $fallback) {
            return [$activeLocale];
        }

        $locales = [$activeLocale];
        $tenantId = $this->tenants->id();

        if ($tenantId !== null) {
            $tenantDefaultLocale = $this->tenantDefaultLocale($tenantId);

            if ($tenantDefaultLocale !== null) {
                $locales[] = $tenantDefaultLocale;
            }
        }

        $locales[] = 'en';

        return array_values(array_unique($locales));
    }

    public function clearRequestCache(): void
    {
        $this->tenantDefaultLocales = [];
    }

    public function currentTenantDefaultLocale(): ?string
    {
        $tenantId = $this->tenants->id();

        if ($tenantId === null) {
            return null;
        }

        return $this->tenantDefaultLocale($tenantId);
    }

    public function canUseTenantOverride(string $candidateLocale, string $activeLocale, bool $fallback): bool
    {
        $tenantId = $this->tenants->id();

        if ($tenantId === null) {
            return false;
        }

        if ($candidateLocale === $activeLocale) {
            return true;
        }

        if (! $fallback) {
            return false;
        }

        return $candidateLocale === $this->tenantDefaultLocale($tenantId);
    }

    private function tenantDefaultLocale(int $tenantId): ?string
    {
        if (array_key_exists($tenantId, $this->tenantDefaultLocales)) {
            return $this->tenantDefaultLocales[$tenantId];
        }

        $settings = $this->settings->settingsFor($tenantId);
        $locale = $settings['default_locale'] ?? null;

        if (! is_string($locale) || ! in_array($locale, ['hy', 'ru', 'en'], true)) {
            $locale = null;
        }

        $this->tenantDefaultLocales[$tenantId] = $locale;

        return $locale;
    }
}
