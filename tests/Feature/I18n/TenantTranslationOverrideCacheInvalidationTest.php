<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Models\Permission;
use App\Modules\Identity\Infrastructure\Models\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Modules\Tenancy\Infrastructure\Models\Tenant;
use App\Support\I18n\Application\ResetTenantTranslationOverride;
use App\Support\I18n\Application\SetTenantTranslationOverride;
use App\Support\I18n\TenantTranslationOverrideCacheKey;
use App\Support\I18n\TenantTranslationOverridePermissions;
use App\Support\I18n\TenantTranslationOverrides;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Cache::flush();
    app(TenantTranslationOverrides::class)->clearRequestCache();
    app(TenantResolver::class)->clear();
    app()->setLocale('en');
});

afterEach(function (): void {
    Cache::flush();
    app(TenantTranslationOverrides::class)->clearRequestCache();
    app(TenantResolver::class)->clear();
    app()->setLocale('en');
});

it('makes a first-ever override visible after an empty presence marker is warm', function (): void {
    $user = i18nCacheUser('translation-cache-first');

    $this->actingAs($user);
    app(TenantResolver::class)->set((int) $user->tenant_id);
    app()->setLocale('en');

    expect(__('admin.dashboard.title'))->toBe('Dashboard')
        ->and(Cache::get(TenantTranslationOverrideCacheKey::localesForTenant((int) $user->tenant_id)))->toBe([]);

    app(SetTenantTranslationOverride::class)($user, 'en', 'admin.dashboard.title', 'First tenant dashboard');

    expect(__('admin.dashboard.title'))->toBe('First tenant dashboard')
        ->and(Cache::get(TenantTranslationOverrideCacheKey::localesForTenant((int) $user->tenant_id)))->toBe(['en']);
});

it('makes an added override visible when the tenant already has cached overrides', function (): void {
    $user = i18nCacheUser('translation-cache-add');

    $this->actingAs($user);
    app(TenantResolver::class)->set((int) $user->tenant_id);
    app()->setLocale('en');

    app(SetTenantTranslationOverride::class)($user, 'en', 'admin.dashboard.title', 'Cached tenant dashboard');

    expect(__('admin.dashboard.title'))->toBe('Cached tenant dashboard');

    app(SetTenantTranslationOverride::class)($user, 'en', 'admin.dashboard.eyebrow', 'Cached tenant workspace');

    expect(__('admin.dashboard.eyebrow'))->toBe('Cached tenant workspace');
});

it('makes an edited override value visible immediately', function (): void {
    $user = i18nCacheUser('translation-cache-edit');

    $this->actingAs($user);
    app(TenantResolver::class)->set((int) $user->tenant_id);
    app()->setLocale('en');

    app(SetTenantTranslationOverride::class)($user, 'en', 'admin.dashboard.title', 'Old tenant dashboard');

    expect(__('admin.dashboard.title'))->toBe('Old tenant dashboard');

    app(SetTenantTranslationOverride::class)($user, 'en', 'admin.dashboard.title', 'New tenant dashboard');

    expect(__('admin.dashboard.title'))->toBe('New tenant dashboard');
});

it('returns to the language file immediately when an override is reset', function (): void {
    $user = i18nCacheUser('translation-cache-reset');

    $this->actingAs($user);
    app(TenantResolver::class)->set((int) $user->tenant_id);
    app()->setLocale('en');

    app(SetTenantTranslationOverride::class)($user, 'en', 'admin.dashboard.title', 'Reset tenant dashboard');

    expect(__('admin.dashboard.title'))->toBe('Reset tenant dashboard');

    app(ResetTenantTranslationOverride::class)($user, 'en', 'admin.dashboard.title');

    expect(__('admin.dashboard.title'))->toBe('Dashboard');
});

it('returns the presence marker to empty after the last override is reset', function (): void {
    $user = i18nCacheUser('translation-cache-last-reset');
    $tenantId = (int) $user->tenant_id;

    $this->actingAs($user);
    app(TenantResolver::class)->set($tenantId);
    app()->setLocale('en');

    app(SetTenantTranslationOverride::class)($user, 'en', 'admin.dashboard.title', 'Last tenant dashboard');

    expect(__('admin.dashboard.title'))->toBe('Last tenant dashboard')
        ->and(Cache::get(TenantTranslationOverrideCacheKey::localesForTenant($tenantId)))->toBe(['en']);

    app(ResetTenantTranslationOverride::class)($user, 'en', 'admin.dashboard.title');

    expect(__('admin.dashboard.title'))->toBe('Dashboard')
        ->and(Cache::get(TenantTranslationOverrideCacheKey::localesForTenant($tenantId)))->toBe([]);
});

it('does not disturb another locale cache when writing one locale', function (): void {
    $user = i18nCacheUser('translation-cache-locale-isolation');
    $tenantId = (int) $user->tenant_id;

    $this->actingAs($user);
    app(TenantResolver::class)->set($tenantId);

    app(SetTenantTranslationOverride::class)($user, 'ru', 'admin.dashboard.title', 'RU tenant dashboard');

    app()->setLocale('ru');
    expect(__('admin.dashboard.title'))->toBe('RU tenant dashboard');

    $ruMapBefore = Cache::get(TenantTranslationOverrideCacheKey::forTenantLocale($tenantId, 'ru'));

    app(SetTenantTranslationOverride::class)($user, 'en', 'admin.dashboard.title', 'EN tenant dashboard');

    expect(Cache::get(TenantTranslationOverrideCacheKey::forTenantLocale($tenantId, 'ru')))->toBe($ruMapBefore);

    app()->setLocale('ru');
    expect(__('admin.dashboard.title'))->toBe('RU tenant dashboard');
});

it('does not disturb another tenant cache when writing one tenant', function (): void {
    $tenantAUser = i18nCacheUser('translation-cache-tenant-a');
    $tenantBUser = i18nCacheUser('translation-cache-tenant-b');

    $this->actingAs($tenantBUser);
    app(TenantResolver::class)->set((int) $tenantBUser->tenant_id);
    app()->setLocale('en');

    app(SetTenantTranslationOverride::class)($tenantBUser, 'en', 'admin.dashboard.title', 'Tenant B dashboard');

    expect(__('admin.dashboard.title'))->toBe('Tenant B dashboard');

    $this->actingAs($tenantAUser);
    app(TenantResolver::class)->set((int) $tenantAUser->tenant_id);

    app(SetTenantTranslationOverride::class)($tenantAUser, 'en', 'admin.dashboard.title', 'Tenant A dashboard');

    $this->actingAs($tenantBUser);
    app(TenantResolver::class)->set((int) $tenantBUser->tenant_id);

    expect(__('admin.dashboard.title'))->toBe('Tenant B dashboard');
});

it('exposes one public tenant-locale cache invalidation entry point', function (): void {
    $methods = collect((new ReflectionClass(TenantTranslationOverrides::class))->getMethods(ReflectionMethod::IS_PUBLIC))
        ->map(fn (ReflectionMethod $method): string => $method->getName())
        ->all();

    expect($methods)->toContain('invalidateTenantLocaleAfterWrite')
        ->and($methods)->not->toContain('forgetTenantLocaleCache')
        ->and($methods)->not->toContain('forgetTenantPresenceCache')
        ->and($methods)->not->toContain('markTenantLocaleHasOverrides')
        ->and($methods)->not->toContain('forget');
});

function i18nCacheUser(string $slug): User
{
    $tenant = Tenant::query()->create([
        'name' => str($slug)->headline()->toString(),
        'slug' => $slug,
        'default_locale' => 'hy',
        'currency' => 'AMD',
        'status' => 'active',
    ]);

    app(TenantResolver::class)->set((int) $tenant->id);

    $role = Role::query()->create([
        'code' => "{$slug}-manager",
        'name' => "{$slug} Manager",
    ]);

    $permission = Permission::query()->create([
        'code' => TenantTranslationOverridePermissions::MANAGE,
        'name' => TenantTranslationOverridePermissions::MANAGE,
    ]);

    $role->permissions()->attach((int) $permission->id, ['tenant_id' => (int) $tenant->id]);

    $user = User::query()->create([
        'role_id' => (int) $role->id,
        'name' => str($slug)->headline()->toString(),
        'email' => "{$slug}@smartrest.test",
        'username' => $slug,
        'default_locale' => 'hy',
        'active' => true,
        'password' => Hash::make('password'),
    ]);

    app(TenantResolver::class)->clear();

    return $user;
}
