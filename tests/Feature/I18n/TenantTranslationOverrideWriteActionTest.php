<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Models\Permission;
use App\Modules\Identity\Infrastructure\Models\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Modules\Tenancy\Infrastructure\Models\Tenant;
use App\Support\Audit\AuditLog;
use App\Support\I18n\Application\ResetTenantTranslationOverride;
use App\Support\I18n\Application\SetTenantTranslationOverride;
use App\Support\I18n\TenantTranslationOverride;
use App\Support\I18n\TenantTranslationOverrideException;
use App\Support\I18n\TenantTranslationOverridePermissions;
use App\Support\I18n\TenantTranslationOverrideRules;
use App\Support\Logging\LogContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

afterEach(function (): void {
    app(TenantResolver::class)->clear();
    LogContext::clear();
});

it('sets updates and resets a tenant translation override through application actions', function (): void {
    $user = i18nWriteUser('translation-actions', [TenantTranslationOverridePermissions::MANAGE]);

    $this->actingAs($user);
    app(TenantResolver::class)->set((int) $user->tenant_id);

    $created = app(SetTenantTranslationOverride::class)($user, 'en', 'admin.dashboard.title', 'Tenant dashboard');

    expect($created->override_value)->toBe('Tenant dashboard')
        ->and(TenantTranslationOverride::query()->count())->toBe(1);

    $updated = app(SetTenantTranslationOverride::class)($user, 'en', 'admin.dashboard.title', 'Updated tenant dashboard');

    expect((int) $updated->id)->toBe((int) $created->id)
        ->and($updated->override_value)->toBe('Updated tenant dashboard')
        ->and(TenantTranslationOverride::query()->count())->toBe(1);

    $removed = app(ResetTenantTranslationOverride::class)($user, 'en', 'admin.dashboard.title');

    expect($removed)->toBeTrue()
        ->and(TenantTranslationOverride::query()->count())->toBe(0);
});

it('rejects invalid set inputs with stable tenant translation override error codes', function (): void {
    $user = i18nWriteUser('translation-validation', [TenantTranslationOverridePermissions::MANAGE]);

    $this->actingAs($user);
    app(TenantResolver::class)->set((int) $user->tenant_id);

    expectI18nWriteError(
        fn (): mixed => app(SetTenantTranslationOverride::class)($user, 'es', 'admin.dashboard.title', 'Spanish dashboard'),
        'admin.translation_overrides.errors.invalid_locale',
    );

    expectI18nWriteError(
        fn (): mixed => app(SetTenantTranslationOverride::class)($user, 'en', 'admin.missing.translation_key', 'Missing key'),
        'admin.translation_overrides.errors.translation_key_missing',
    );

    expectI18nWriteError(
        fn (): mixed => app(SetTenantTranslationOverride::class)($user, 'en', 'auth.failed', 'Softer auth copy'),
        'admin.translation_overrides.errors.key_not_overridable',
    );

    expectI18nWriteError(
        fn (): mixed => app(SetTenantTranslationOverride::class)($user, 'en', 'admin.dashboard.title', str_repeat('a', TenantTranslationOverrideRules::MAX_VALUE_LENGTH + 1)),
        'admin.translation_overrides.errors.value_too_long',
    );

    expect(TenantTranslationOverride::query()->count())->toBe(0);
});

it('rejects invalid reset inputs with stable tenant translation override error codes', function (): void {
    $user = i18nWriteUser('translation-reset-validation', [TenantTranslationOverridePermissions::MANAGE]);

    $this->actingAs($user);
    app(TenantResolver::class)->set((int) $user->tenant_id);

    expectI18nWriteError(
        fn (): mixed => app(ResetTenantTranslationOverride::class)($user, 'es', 'admin.dashboard.title'),
        'admin.translation_overrides.errors.invalid_locale',
    );

    expectI18nWriteError(
        fn (): mixed => app(ResetTenantTranslationOverride::class)($user, 'en', 'admin.missing.translation_key'),
        'admin.translation_overrides.errors.translation_key_missing',
    );

    expectI18nWriteError(
        fn (): mixed => app(ResetTenantTranslationOverride::class)($user, 'en', 'auth.failed'),
        'admin.translation_overrides.errors.key_not_overridable',
    );
});

it('denies write actions without the tenant translation override permission', function (): void {
    $user = i18nWriteUser('translation-action-denied', []);

    $this->actingAs($user);
    app(TenantResolver::class)->set((int) $user->tenant_id);

    expect(fn (): TenantTranslationOverride => app(SetTenantTranslationOverride::class)($user, 'en', 'admin.dashboard.title', 'Denied'))
        ->toThrow(AuthorizationException::class);

    expect(TenantTranslationOverride::query()->count())->toBe(0);
});

it('denies tenant translation override writes for another tenant context', function (): void {
    $tenantAUser = i18nWriteUser('translation-tenant-a', [TenantTranslationOverridePermissions::MANAGE]);
    $tenantBUser = i18nWriteUser('translation-tenant-b', [TenantTranslationOverridePermissions::MANAGE]);

    $this->actingAs($tenantAUser);
    app(TenantResolver::class)->set((int) $tenantBUser->tenant_id);

    expect(fn (): TenantTranslationOverride => app(SetTenantTranslationOverride::class)($tenantAUser, 'en', 'admin.dashboard.title', 'Foreign write'))
        ->toThrow(AuthorizationException::class);

    expect(TenantTranslationOverride::withoutGlobalScopes()->count())->toBe(0);
});

it('records before and after audit payloads for set and reset actions', function (): void {
    $user = i18nWriteUser('translation-audit', [TenantTranslationOverridePermissions::MANAGE]);

    $this->actingAs($user);
    app(TenantResolver::class)->set((int) $user->tenant_id);

    LogContext::start('i18n-audit-test', 'i18n');

    $created = app(SetTenantTranslationOverride::class)($user, 'en', 'admin.dashboard.title', 'Tenant dashboard');
    $updated = app(SetTenantTranslationOverride::class)($user, 'en', 'admin.dashboard.title', 'Updated tenant dashboard');

    expect((int) $updated->id)->toBe((int) $created->id);

    app(ResetTenantTranslationOverride::class)($user, 'en', 'admin.dashboard.title');

    $audits = AuditLog::query()->orderBy('id')->get();

    expect($audits)->toHaveCount(3)
        ->and($audits[0]->action)->toBe('tenant_translation_override.set')
        ->and($audits[0]->before_json)->toBeNull()
        ->and($audits[0]->after_json)->toMatchArray([
            'locale' => 'en',
            'translation_key' => 'admin.dashboard.title',
            'override_value' => 'Tenant dashboard',
        ])
        ->and($audits[1]->action)->toBe('tenant_translation_override.set')
        ->and($audits[1]->before_json)->toMatchArray([
            'override_value' => 'Tenant dashboard',
        ])
        ->and($audits[1]->after_json)->toMatchArray([
            'override_value' => 'Updated tenant dashboard',
        ])
        ->and($audits[2]->action)->toBe('tenant_translation_override.reset')
        ->and($audits[2]->before_json)->toMatchArray([
            'override_value' => 'Updated tenant dashboard',
        ])
        ->and($audits[2]->after_json)->toBe(['deleted' => true]);
});

function expectI18nWriteError(Closure $callback, string $errorCode): void
{
    try {
        $callback();
    } catch (TenantTranslationOverrideException $exception) {
        expect($exception->errorCode())->toBe($errorCode);

        return;
    }

    test()->fail("Expected tenant translation override error [{$errorCode}].");
}

/**
 * @param  list<string>  $permissionCodes
 */
function i18nWriteUser(string $slug, array $permissionCodes): User
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

    $permissions = collect($permissionCodes)
        ->map(fn (string $code): Permission => Permission::query()->create([
            'code' => $code,
            'name' => $code,
        ]));

    if ($permissions->isNotEmpty()) {
        $role->permissions()->attach(
            $permissions->pluck('id')->all(),
            ['tenant_id' => (int) $tenant->id],
        );
    }

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
