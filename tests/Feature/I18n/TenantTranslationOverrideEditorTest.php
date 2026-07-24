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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Features\SupportTesting\Testable;
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

it('keeps special translation values out of JavaScript evaluated attributes', function (): void {
    $records = translationEditorUser('translation-editor-js-encoding', [TenantTranslationOverridePermissions::MANAGE]);
    translationEditorContext($records);

    $valuesByKey = [
        'admin.brand.tagline' => "Chef's dashboard",
        'admin.dashboard.eyebrow' => 'Quote "dashboard"',
        'admin.dashboard.metrics.categories.label' => 'Path C:\\kitchen',
        'admin.dashboard.metrics.items.label' => "Line one\nLine two",
        'admin.dashboard.subtitle' => 'Unicode խոհանոց marker',
        'admin.dashboard.title' => '<strong>Markup marker</strong>',
    ];

    foreach ($valuesByKey as $key => $value) {
        TenantTranslationOverride::query()->create([
            'locale' => 'en',
            'translation_key' => $key,
            'override_value' => $value,
        ]);
    }

    app(TenantTranslationOverrides::class)->clearRequestCache();

    $response = $this->actingAs($records['user'])
        ->withSession(['branch_id' => (int) $records['branch']->id])
        ->get(route('admin.translation-overrides.index', ['locale' => 'en']));

    $response->assertOk();

    $html = $response->getContent();
    $javascriptAttributes = translationEditorJavascriptEvaluatedAttributes($html);
    $startEditingAttributes = array_values(array_filter(
        translationEditorJavascriptEvaluatedAttributeList($html),
        fn (string $attribute): bool => str_contains($attribute, 'startEditing('),
    ));

    foreach ($valuesByKey as $value) {
        expect($html)->toContain(e($value));
    }

    foreach (['Chef', 'Quote', 'Path C:', 'Line one', 'Unicode', 'Markup marker'] as $marker) {
        expect($javascriptAttributes)->not->toContain($marker);
    }

    foreach ($startEditingAttributes as $attribute) {
        expect($attribute)->not->toContain(',');
    }

    expect($startEditingAttributes)->not->toBeEmpty()
        ->and(implode("\n", $startEditingAttributes))->toContain('admin.dashboard.title')
        ->and($html)->toContain('&lt;strong&gt;Markup marker&lt;/strong&gt;')
        ->and($html)->not->toContain('<strong>Markup marker</strong>');
});

it('edits and resets an override through the existing actions while preserving URL-backed state', function (): void {
    $records = translationEditorUser('translation-editor-write', [TenantTranslationOverridePermissions::MANAGE]);
    translationEditorContext($records);

    Livewire::withQueryParams(['locale' => 'en', 'q' => 'dashboard', 'page' => 1])
        ->actingAs($records['user'])
        ->test(TranslationOverridesEditor::class)
        ->call('startEditing', 'admin.dashboard.title')
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
        ->set('editingKey', 'menu.confirm.force_delete_item_message')
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
        ->set('editingKey', 'admin.translation_overrides.actions.reset')
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
        ->call('startEditing', 'admin.dashboard.title')
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
        ->call('startEditing', 'admin.dashboard.title')
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

it('keeps editor render query count independent of rendered result size', function (): void {
    $records = translationEditorUser('translation-editor-query-count', [TenantTranslationOverridePermissions::MANAGE]);

    $small = translationEditorRenderQueryCount(
        $records,
        ['locale' => 'en', 'q' => 'dashboard.title'],
        fn (Testable $component): Testable => $component
            ->assertSee('admin.dashboard.title', false)
            ->assertDontSee('admin.dashboard.heading', false),
    );
    $large = translationEditorRenderQueryCount(
        $records,
        ['locale' => 'en'],
        fn (Testable $component): Testable => $component
            ->assertSee('admin.dashboard.title', false)
            ->assertSee('admin.dashboard.heading', false)
            ->assertSee('admin.dashboard.subtitle', false),
    );

    expect($small['total'])->toBe($large['total'])
        ->and($small['override_reads'])->toBe($large['override_reads'])
        ->and($small['override_reads'])->toBeLessThanOrEqual(3);
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

function translationEditorJavascriptEvaluatedAttributes(string $html): string
{
    return implode("\n", translationEditorJavascriptEvaluatedAttributeList($html));
}

/**
 * @return list<string>
 */
function translationEditorJavascriptEvaluatedAttributeList(string $html): array
{
    preg_match_all(
        '/\s(?:wire:click|x-data|x-on:[A-Za-z0-9_:.\-]+|@[A-Za-z][A-Za-z0-9_:.\-]*|onclick)=([\'"])(.*?)\1/s',
        $html,
        $matches,
    );

    return array_map(
        fn (string $attribute): string => html_entity_decode($attribute, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        $matches[2],
    );
}

/**
 * @param  array{tenant: Tenant, branch: Branch, user: User}  $records
 * @param  array<string, mixed>  $queryParams
 * @param  callable(Testable<TranslationOverridesEditor>): Testable<TranslationOverridesEditor>  $assertions
 * @return array{total: int, override_reads: int}
 */
function translationEditorRenderQueryCount(array $records, array $queryParams, callable $assertions): array
{
    Cache::flush();
    app(TenantTranslationOverrides::class)->clearRequestCache();
    translationEditorContext($records);
    $records['user']->loadMissing('role.permissions');

    DB::flushQueryLog();
    DB::enableQueryLog();

    try {
        /** @var Testable<TranslationOverridesEditor> $component */
        $component = Livewire::withQueryParams($queryParams)
            ->actingAs($records['user'])
            ->test(TranslationOverridesEditor::class);
        $assertions($component);

        $queries = DB::getQueryLog();

        return [
            'total' => count($queries),
            'override_reads' => collect($queries)
                ->filter(fn (array $query): bool => str_contains((string) ($query['query'] ?? ''), 'tenant_translation_overrides'))
                ->count(),
        ];
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }
}
