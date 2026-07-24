<?php

declare(strict_types=1);

use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Modules\Tenancy\Infrastructure\Models\Tenant;
use App\Support\I18n\Application\SearchTenantTranslationOverrides;
use App\Support\I18n\LanguageFileTranslationCatalogue;
use App\Support\I18n\TenantTranslationOverride;
use App\Support\I18n\TenantTranslationOverrides;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

afterEach(function (): void {
    Cache::flush();
    app(TenantTranslationOverrides::class)->clearRequestCache();
    app(TenantResolver::class)->clear();
});

it('changes the language file catalogue cache key when locale files change', function (): void {
    $files = new Filesystem;
    $directory = storage_path('framework/testing/lang-catalogue/en');
    $path = "{$directory}/admin.php";

    $files->ensureDirectoryExists($directory);
    $files->put($path, "<?php\nreturn ['first' => 'First'];\n");
    touch($path, 1_800_000_000);

    $catalogue = new LanguageFileTranslationCatalogue(
        app('translation.loader'),
        $files,
        storage_path('framework/testing/lang-catalogue'),
    );
    $firstKey = $catalogue->cacheKeyForLocale('en');

    $files->put($path, "<?php\nreturn ['first' => 'First', 'second' => 'Second'];\n");
    touch($path, 1_800_000_100);

    expect($catalogue->cacheKeyForLocale('en'))->not->toBe($firstKey);

    $files->deleteDirectory(storage_path('framework/testing/lang-catalogue'));
});

it('searches editable translation rows by effective visible text and key fragment', function (): void {
    i18nCatalogueTenant('translation-catalogue-search');

    $search = app(SearchTenantTranslationOverrides::class);

    expect($search('hy', 'վահանակ', 1, 15)->total())->toBeGreaterThan(0)
        ->and($search('ru', 'панель', 1, 15)->total())->toBeGreaterThan(0)
        ->and($search('en', 'dashboard', 1, 15)->total())->toBeGreaterThan(0)
        ->and($search('en', 'dashboard.title', 1, 15)->total())->toBeGreaterThan(0);
});

it('overlays tenant overrides in search results and hides non-overridable keys', function (): void {
    i18nCatalogueTenant('translation-catalogue-overrides');

    TenantTranslationOverride::query()->create([
        'locale' => 'en',
        'translation_key' => 'admin.dashboard.title',
        'override_value' => 'Control room',
    ]);
    TenantTranslationOverride::query()->create([
        'locale' => 'en',
        'translation_key' => 'auth.login.submit',
        'override_value' => 'Unsafe login copy',
    ]);
    app(TenantTranslationOverrides::class)->clearRequestCache();

    $rows = app(SearchTenantTranslationOverrides::class)('en', 'control room', 1, 15);
    $row = collect($rows->items())->firstWhere('key', 'admin.dashboard.title');

    expect($row)->not->toBeNull()
        ->and($row->effectiveValue)->toBe('Control room')
        ->and($row->overridden)->toBeTrue()
        ->and($row->values['en'])->toBe('Control room')
        ->and($row->languageValues['en'])->toBe('Dashboard')
        ->and(app(SearchTenantTranslationOverrides::class)('en', 'Unsafe login copy', 1, 15)->total())->toBe(0);
});

function i18nCatalogueTenant(string $slug): Tenant
{
    $tenant = Tenant::query()->create([
        'name' => str($slug)->headline()->toString(),
        'slug' => $slug,
        'default_locale' => 'en',
        'currency' => 'AMD',
        'status' => 'active',
    ]);

    app(TenantResolver::class)->set((int) $tenant->id);

    return $tenant;
}
