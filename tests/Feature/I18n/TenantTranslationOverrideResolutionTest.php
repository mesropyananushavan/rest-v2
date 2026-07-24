<?php

declare(strict_types=1);

use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Modules\Tenancy\Infrastructure\Models\Tenant;
use App\Support\I18n\NonOverridableTranslationKeys;
use App\Support\I18n\TenantTranslationLocaleFallbacks;
use App\Support\I18n\TenantTranslationOverride;
use App\Support\I18n\TenantTranslationOverrides;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Cache::flush();
    app(TenantTranslationOverrides::class)->clearRequestCache();
    app(TenantTranslationLocaleFallbacks::class)->clearRequestCache();
    app(TenantResolver::class)->clear();
    app()->setLocale('en');
});

afterEach(function (): void {
    Cache::flush();
    app(TenantTranslationOverrides::class)->clearRequestCache();
    app(TenantTranslationLocaleFallbacks::class)->clearRequestCache();
    app(TenantResolver::class)->clear();
    app()->setLocale('en');
});

it('resolves UI translations through tenant overrides and language file fallbacks in order', function (): void {
    $tenant = i18nOverrideTenant('i18n-resolution-order', 'hy');

    app(TenantResolver::class)->set((int) $tenant->id);
    app()->setLocale('ru');

    Lang::addLines([
        'admin.i18n_resolution.active_override' => 'Active locale file',
        'admin.i18n_resolution.active_file' => 'Active locale file',
    ], 'ru');
    Lang::addLines([
        'admin.i18n_resolution.active_override' => 'Tenant default file',
        'admin.i18n_resolution.active_file' => 'Tenant default file',
        'admin.i18n_resolution.default_file' => 'Tenant default file',
    ], 'hy');
    Lang::addLines([
        'admin.i18n_resolution.active_override' => 'English file',
        'admin.i18n_resolution.active_file' => 'English file',
        'admin.i18n_resolution.default_override' => 'English file',
        'admin.i18n_resolution.default_file' => 'English file',
        'admin.i18n_resolution.english_file' => 'English file',
    ], 'en');

    i18nOverride('ru', 'admin.i18n_resolution.active_override', 'Active locale tenant override');
    i18nOverride('hy', 'admin.i18n_resolution.active_override', 'Tenant default override');
    i18nOverride('hy', 'admin.i18n_resolution.active_file', 'Tenant default override');
    i18nOverride('hy', 'admin.i18n_resolution.default_override', 'Tenant default override');

    expect(__('admin.i18n_resolution.active_override'))->toBe('Active locale tenant override')
        ->and(__('admin.i18n_resolution.active_file'))->toBe('Active locale file')
        ->and(__('admin.i18n_resolution.default_override'))->toBe('Tenant default override')
        ->and(__('admin.i18n_resolution.default_file'))->toBe('Tenant default file')
        ->and(__('admin.i18n_resolution.english_file'))->toBe('English file');
});

it('ignores database overrides for non-overridable safety and auth keys', function (): void {
    $tenant = i18nOverrideTenant('i18n-non-overridable', 'en');

    app(TenantResolver::class)->set((int) $tenant->id);
    app()->setLocale('en');

    i18nOverride('en', 'auth.login.heading', 'Unsafe login copy');
    i18nOverride('en', 'menu.confirm.force_delete_item_message', 'Unsafe delete copy');

    expect(app(NonOverridableTranslationKeys::class)->contains('auth.login.heading'))->toBeTrue()
        ->and(app(NonOverridableTranslationKeys::class)->contains('menu.confirm.force_delete_item_message'))->toBeTrue()
        ->and(__('auth.login.heading'))->toBe('Sign in to SmartRest')
        ->and(__('menu.confirm.force_delete_item_message'))->toBe('This permanently deletes the archived item. This action is irreversible.');
});

it('applies replacement parameters and pluralization to tenant overrides', function (): void {
    $tenant = i18nOverrideTenant('i18n-replacements-plural', 'en');

    app(TenantResolver::class)->set((int) $tenant->id);
    app()->setLocale('en');

    i18nOverride('en', 'admin.i18n_runtime.replaced', 'Tenant dashboard for :name');
    i18nOverride('en', 'admin.i18n_runtime.plural', '{1} One tenant order for :name|[2,*] :count tenant orders for :name');

    expect(__('admin.i18n_runtime.replaced', ['name' => 'Arat']))->toBe('Tenant dashboard for Arat')
        ->and(trans_choice('admin.i18n_runtime.plural', 2, ['name' => 'Arat']))->toBe('2 tenant orders for Arat');
});

it('loads tenant overrides at most once per tenant locale while rendering many translation keys', function (): void {
    $tenant = i18nOverrideTenant('i18n-query-count-overrides', 'hy');

    app(TenantResolver::class)->set((int) $tenant->id);
    app()->setLocale('hy');

    foreach (range(1, 200) as $index) {
        i18nOverride('hy', "admin.i18n_bulk.key_{$index}", "Tenant value {$index}");
    }

    $overrideReads = i18nOverrideQueryCount(function (): void {
        foreach (range(1, 200) as $index) {
            expect(__("admin.i18n_bulk.key_{$index}"))->toBe("Tenant value {$index}");
        }
    });

    expect($overrideReads)->toBe(1);
});

it('loads tenant overrides from the database when the cache is cold', function (): void {
    $tenant = i18nOverrideTenant('i18n-cold-cache', 'en');

    app(TenantResolver::class)->set((int) $tenant->id);
    app()->setLocale('en');

    i18nOverride('en', 'admin.i18n_cold_cache.heading', 'Cold cache tenant heading');

    Cache::flush();
    app(TenantTranslationOverrides::class)->clearRequestCache();

    $overrideReads = i18nOverrideQueryCount(function (): void {
        expect(__('admin.i18n_cold_cache.heading'))->toBe('Cold cache tenant heading');
    });

    expect($overrideReads)->toBe(1);
});

it('adds no per-key override reads when a tenant has zero overrides', function (): void {
    $tenant = i18nOverrideTenant('i18n-query-count-empty', 'hy');

    app(TenantResolver::class)->set((int) $tenant->id);
    app()->setLocale('hy');

    $overrideReads = i18nOverrideQueryCount(function (): void {
        foreach (range(1, 200) as $index) {
            expect(__("admin.i18n_missing.key_{$index}"))->toBe("admin.i18n_missing.key_{$index}");
        }
    });

    expect($overrideReads)->toBe(0);
});

it('performs zero database queries for translation when no tenant is resolved', function (): void {
    $tenant = i18nOverrideTenant('i18n-no-tenant', 'en');

    app(TenantResolver::class)->set((int) $tenant->id);
    i18nOverride('en', 'admin.dashboard.title', 'Tenant dashboard');
    app(TenantResolver::class)->clear();
    app()->setLocale('en');

    DB::flushQueryLog();
    DB::enableQueryLog();

    try {
        foreach (range(1, 50) as $index) {
            expect(__('admin.dashboard.title'))->toBe('Dashboard');
            expect(__("admin.i18n_no_tenant.missing_{$index}"))->toBe("admin.i18n_no_tenant.missing_{$index}");
        }

        expect(DB::getQueryLog())->toHaveCount(0);
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }
});

it('does not leak overrides between tenants across sequential resolutions in one process', function (): void {
    $tenantA = i18nOverrideTenant('i18n-tenant-a', 'hy');

    app(TenantResolver::class)->set((int) $tenantA->id);
    i18nOverride('hy', 'admin.dashboard.title', 'Tenant A dashboard');

    $tenantB = i18nOverrideTenant('i18n-tenant-b', 'hy');

    app(TenantResolver::class)->set((int) $tenantB->id);
    i18nOverride('hy', 'admin.dashboard.title', 'Tenant B dashboard');

    app()->setLocale('hy');

    app(TenantResolver::class)->set((int) $tenantA->id);
    expect(__('admin.dashboard.title'))->toBe('Tenant A dashboard');

    app(TenantResolver::class)->set((int) $tenantB->id);
    expect(__('admin.dashboard.title'))->toBe('Tenant B dashboard');

    app(TenantResolver::class)->set((int) $tenantA->id);
    expect(__('admin.dashboard.title'))->toBe('Tenant A dashboard');
});

function i18nOverrideTenant(string $slug, string $defaultLocale): Tenant
{
    return Tenant::query()->create([
        'name' => str_replace('-', ' ', $slug),
        'slug' => $slug,
        'default_locale' => $defaultLocale,
        'currency' => 'AMD',
        'status' => 'active',
    ]);
}

function i18nOverride(string $locale, string $key, string $value): TenantTranslationOverride
{
    return TenantTranslationOverride::query()->create([
        'locale' => $locale,
        'translation_key' => $key,
        'override_value' => $value,
    ]);
}

/**
 * @param  callable(): void  $callback
 */
function i18nOverrideQueryCount(callable $callback): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    try {
        $callback();

        return collect(DB::getQueryLog())
            ->filter(fn (array $query): bool => str_contains((string) ($query['query'] ?? ''), 'tenant_translation_overrides'))
            ->count();
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }
}
