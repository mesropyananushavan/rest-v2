<?php

declare(strict_types=1);

use App\Livewire\Admin\TranslationOverridesEditor;
use App\Modules\Identity\Infrastructure\Models\Permission;
use App\Modules\Identity\Infrastructure\Models\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Identity\Infrastructure\Models\UserBranchAssignment;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Modules\Tenancy\Infrastructure\Models\Branch;
use App\Modules\Tenancy\Infrastructure\Models\Tenant;
use App\Support\I18n\TenantTranslationOverride;
use App\Support\I18n\TenantTranslationOverridePermissions;
use App\Support\I18n\TenantTranslationOverrides;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

afterEach(function (): void {
    Cache::flush();
    app(TenantTranslationOverrides::class)->clearRequestCache();
    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();
    app()->setLocale('en');
});

it('renders the translation override screen through permission-gated admin navigation', function (): void {
    $records = translationEditorUser('translation-editor-route', [TenantTranslationOverridePermissions::MANAGE]);

    $this->actingAs($records['user'])
        ->withSession(['branch_id' => (int) $records['branch']->id])
        ->get(route('admin.translation-overrides.index'))
        ->assertOk()
        ->assertSee('wire:name="admin.translation-overrides-editor"', false)
        ->assertSee(__('admin.nav.translation_overrides'), false)
        ->assertSee(__('admin.translation_overrides.heading'), false)
        ->assertSee(__('admin.translation_overrides.search.label'), false);
});

it('searches by effective visible text in Armenian Russian and English', function (): void {
    $records = translationEditorUser('translation-editor-search', [TenantTranslationOverridePermissions::MANAGE]);
    translationEditorContext($records);

    Livewire::withQueryParams(['locale' => 'hy', 'q' => 'վահանակ'])
        ->actingAs($records['user'])
        ->test(TranslationOverridesEditor::class)
        ->assertSee('admin.dashboard.title', false)
        ->assertSee('Վահանակ', false);

    Livewire::withQueryParams(['locale' => 'ru', 'q' => 'панель'])
        ->actingAs($records['user'])
        ->test(TranslationOverridesEditor::class)
        ->assertSee('admin.dashboard.title', false)
        ->assertSee('Панель', false);

    Livewire::withQueryParams(['locale' => 'en', 'q' => 'dashboard'])
        ->actingAs($records['user'])
        ->test(TranslationOverridesEditor::class)
        ->assertSee('admin.dashboard.title', false)
        ->assertSee('Dashboard', false);
});

it('searches by key fragment and renders row state with all locale values', function (): void {
    $records = translationEditorUser('translation-editor-row', [TenantTranslationOverridePermissions::MANAGE]);
    translationEditorContext($records);

    TenantTranslationOverride::query()->create([
        'locale' => 'en',
        'translation_key' => 'admin.dashboard.title',
        'override_value' => 'Control room',
    ]);
    app(TenantTranslationOverrides::class)->clearRequestCache();

    Livewire::withQueryParams(['locale' => 'en', 'q' => 'dashboard.title'])
        ->actingAs($records['user'])
        ->test(TranslationOverridesEditor::class)
        ->assertSee('Control room', false)
        ->assertSee('admin.dashboard.title', false)
        ->assertSee(__('admin.translation_overrides.status.overridden'), false)
        ->assertSee(__('admin.locales.hy'), false)
        ->assertSee(__('admin.locales.ru'), false)
        ->assertSee(__('admin.locales.en'), false)
        ->assertSee('Վահանակ', false)
        ->assertSee('Панель', false);
});

it('edits and resets an override through the existing actions while preserving URL-backed state', function (): void {
    $records = translationEditorUser('translation-editor-write', [TenantTranslationOverridePermissions::MANAGE]);
    translationEditorContext($records);

    Livewire::withQueryParams(['locale' => 'en', 'q' => 'dashboard', 'page' => 1])
        ->actingAs($records['user'])
        ->test(TranslationOverridesEditor::class)
        ->call('startEditing', 'admin.dashboard.title', 'Dashboard')
        ->set('overrideValue', 'Ops Center')
        ->call('save')
        ->assertSet('locale', 'en')
        ->assertSet('search', 'dashboard')
        ->assertSet('page', 1)
        ->assertSee(__('admin.translation_overrides.flash.saved'), false)
        ->assertSee('Ops Center', false)
        ->call('resetOverride', 'admin.dashboard.title')
        ->assertSet('locale', 'en')
        ->assertSet('search', 'dashboard')
        ->assertSet('page', 1)
        ->assertSee(__('admin.translation_overrides.flash.reset'), false)
        ->assertSee('Dashboard', false);

    expect(TenantTranslationOverride::query()
        ->where('locale', 'en')
        ->where('translation_key', 'admin.dashboard.title')
        ->exists())->toBeFalse();
});

it('rejects crafted blocked key writes through the editor', function (): void {
    $records = translationEditorUser('translation-editor-blocked', [TenantTranslationOverridePermissions::MANAGE]);
    translationEditorContext($records);

    Livewire::actingAs($records['user'])
        ->test(TranslationOverridesEditor::class)
        ->set('locale', 'en')
        ->call('startEditing', 'menu.confirm.force_delete_item_message', 'Unsafe')
        ->set('overrideValue', 'Hide the danger')
        ->call('save')
        ->assertHasErrors('overrideValue')
        ->assertSee(__('admin.translation_overrides.errors.key_not_overridable'), false);

    expect(TenantTranslationOverride::query()
        ->where('translation_key', 'menu.confirm.force_delete_item_message')
        ->exists())->toBeFalse();
});

it('rejects attempts to override the editor own strings', function (): void {
    $records = translationEditorUser('translation-editor-self', [TenantTranslationOverridePermissions::MANAGE]);
    translationEditorContext($records);

    Livewire::actingAs($records['user'])
        ->test(TranslationOverridesEditor::class)
        ->set('locale', 'en')
        ->call('startEditing', 'admin.translation_overrides.actions.reset', 'Reset')
        ->set('overrideValue', 'Disappear')
        ->call('save')
        ->assertHasErrors('overrideValue')
        ->assertSee(__('admin.translation_overrides.errors.key_not_overridable'), false);

    expect(TenantTranslationOverride::query()
        ->where('translation_key', 'admin.translation_overrides.actions.reset')
        ->exists())->toBeFalse();
});

it('rejects crafted cross-tenant writes through the editor', function (): void {
    $tenantA = translationEditorUser('translation-editor-tenant-a', [], superadmin: true);
    $tenantB = translationEditorUser('translation-editor-tenant-b', [TenantTranslationOverridePermissions::MANAGE]);

    translationEditorContext($tenantB);

    Livewire::actingAs($tenantA['user'])
        ->test(TranslationOverridesEditor::class)
        ->set('locale', 'en')
        ->call('startEditing', 'admin.dashboard.title', 'Dashboard')
        ->set('overrideValue', 'Foreign tenant text')
        ->call('save')
        ->assertStatus(403);

    expect(TenantTranslationOverride::query()
        ->where('translation_key', 'admin.dashboard.title')
        ->exists())->toBeFalse();
});

it('denies users without the permission from viewing or writing through the editor', function (): void {
    $records = translationEditorUser('translation-editor-denied', []);

    $this->actingAs($records['user'])
        ->withSession(['branch_id' => (int) $records['branch']->id])
        ->get(route('admin.translation-overrides.index'))
        ->assertForbidden();

    translationEditorContext($records);

    Livewire::actingAs($records['user'])
        ->test(TranslationOverridesEditor::class)
        ->set('locale', 'en')
        ->call('startEditing', 'admin.dashboard.title', 'Dashboard')
        ->assertStatus(403);

    Livewire::actingAs($records['user'])
        ->test(TranslationOverridesEditor::class)
        ->set('locale', 'en')
        ->set('editingKey', 'admin.dashboard.title')
        ->set('overrideValue', 'Denied text')
        ->call('save')
        ->assertStatus(403);

    expect(TenantTranslationOverride::query()->count())->toBe(0);
});

/**
 * @param  list<string>  $permissionCodes
 * @return array{tenant: Tenant, branch: Branch, user: User}
 */
function translationEditorUser(string $slug, array $permissionCodes, bool $superadmin = false): array
{
    $tenant = Tenant::query()->create([
        'name' => str($slug)->headline()->toString(),
        'slug' => $slug,
        'default_locale' => 'en',
        'currency' => 'AMD',
        'status' => 'active',
    ]);

    app(TenantResolver::class)->set((int) $tenant->id);

    $branch = Branch::query()->create([
        'name' => "{$slug} Branch",
        'timezone' => 'Asia/Yerevan',
        'status' => 'active',
    ]);

    app(BranchContext::class)->set((int) $branch->id);

    $role = Role::query()->create([
        'code' => "{$slug}-role",
        'name' => "{$slug} Role",
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
        'default_locale' => 'en',
        'active' => true,
        'is_superadmin' => $superadmin,
        'password' => Hash::make('password'),
    ]);

    UserBranchAssignment::query()->create([
        'user_id' => (int) $user->id,
        'branch_id' => (int) $branch->id,
    ]);

    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();

    return [
        'tenant' => $tenant,
        'branch' => $branch,
        'user' => $user,
    ];
}

/**
 * @param  array{tenant: Tenant, branch: Branch, user: User}  $records
 */
function translationEditorContext(array $records): void
{
    app(TenantResolver::class)->set((int) $records['tenant']->id);
    app(BranchContext::class)->set((int) $records['branch']->id);
}
