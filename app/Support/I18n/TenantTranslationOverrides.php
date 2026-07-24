<?php

declare(strict_types=1);

namespace App\Support\I18n;

use App\Modules\Tenancy\Contracts\TenantResolver;
use Illuminate\Support\Facades\Cache;

final class TenantTranslationOverrides
{
    /**
     * @var array<string, array<string, string>>
     */
    private array $requestCache = [];

    public function __construct(
        private readonly TenantResolver $tenants,
    ) {}

    public function get(string $locale, string $key): ?string
    {
        $overrides = $this->overridesForLocale($locale);

        return $overrides[$key] ?? null;
    }

    /**
     * @return array<string, string>
     */
    public function overridesForLocale(string $locale): array
    {
        $tenantId = $this->tenants->id();

        if ($tenantId === null) {
            return [];
        }

        $requestKey = "{$tenantId}:{$locale}";

        if (array_key_exists($requestKey, $this->requestCache)) {
            return $this->requestCache[$requestKey];
        }

        $localesWithOverrides = Cache::get(TenantTranslationOverrideCacheKey::localesForTenant($tenantId));

        if (is_array($localesWithOverrides) && ! in_array($locale, $localesWithOverrides, true)) {
            $this->requestCache[$requestKey] = [];

            return [];
        }

        $cacheKey = TenantTranslationOverrideCacheKey::forTenantLocale($tenantId, $locale);

        /** @var array<string, string> $overrides */
        $overrides = Cache::rememberForever($cacheKey, function () use ($locale): array {
            return TenantTranslationOverride::query()
                ->where('locale', $locale)
                ->orderBy('translation_key')
                ->pluck('override_value', 'translation_key')
                ->mapWithKeys(fn (mixed $value, mixed $key): array => is_string($key) && is_string($value) ? [$key => $value] : [])
                ->all();
        });

        $this->requestCache[$requestKey] = $overrides;

        return $overrides;
    }

    public function invalidateTenantLocaleAfterWrite(int $tenantId, string $locale): void
    {
        unset($this->requestCache["{$tenantId}:{$locale}"]);

        Cache::forget(TenantTranslationOverrideCacheKey::forTenantLocale($tenantId, $locale));

        Cache::forever(
            TenantTranslationOverrideCacheKey::localesForTenant($tenantId),
            $this->localesWithOverrides($tenantId),
        );
    }

    public function clearRequestCache(): void
    {
        $this->requestCache = [];
    }

    public static function markTenantHasNoOverrides(int $tenantId): void
    {
        Cache::forever(TenantTranslationOverrideCacheKey::localesForTenant($tenantId), []);
    }

    /**
     * @return list<string>
     */
    private function localesWithOverrides(int $tenantId): array
    {
        // Cache maintenance intentionally bypasses the tenant global scope: it
        // recomputes metadata for the explicit tenant id affected by the write.
        $locales = TenantTranslationOverride::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->select('locale')
            ->distinct()
            ->orderBy('locale')
            ->pluck('locale')
            ->map(fn (mixed $locale): ?string => is_string($locale) ? $locale : null)
            ->filter()
            ->values()
            ->all();

        /** @var list<string> $locales */
        return $locales;
    }
}
