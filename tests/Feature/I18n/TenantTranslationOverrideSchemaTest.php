<?php

declare(strict_types=1);

use App\Modules\Tenancy\Contracts\BelongsToTenant;
use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Modules\Tenancy\Infrastructure\Models\Tenant;
use App\Support\I18n\TenantTranslationOverride;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

afterEach(function (): void {
    app(TenantResolver::class)->clear();
});

it('creates the tenant translation override schema and tenant-scoped model', function (): void {
    expect(Schema::hasTable('tenant_translation_overrides'))->toBeTrue()
        ->and(Schema::hasColumns('tenant_translation_overrides', [
            'id',
            'tenant_id',
            'locale',
            'translation_key',
            'override_value',
            'created_at',
            'updated_at',
        ]))->toBeTrue()
        ->and(class_uses_recursive(TenantTranslationOverride::class))->toContain(BelongsToTenant::class);

    $indexes = collect(Schema::getIndexes('tenant_translation_overrides'));
    $indexNames = $indexes->pluck('name')->all();
    $uniqueColumns = $indexes
        ->filter(fn (array $index): bool => (bool) ($index['unique'] ?? false))
        ->pluck('columns')
        ->all();

    expect($indexNames)->toContain('tenant_translation_overrides_tenant_id_index')
        ->and($indexNames)->toContain('tenant_translation_overrides_tenant_locale_idx')
        ->and($uniqueColumns)->toContain(['tenant_id', 'locale', 'translation_key']);
});

it('keeps translation override keys unique per tenant and locale', function (): void {
    $tenant = Tenant::query()->create([
        'name' => 'Override Tenant',
        'slug' => 'override-tenant',
        'default_locale' => 'hy',
        'currency' => 'AMD',
        'status' => 'active',
    ]);

    app(TenantResolver::class)->set((int) $tenant->id);

    TenantTranslationOverride::query()->create([
        'locale' => 'hy',
        'translation_key' => 'admin.dashboard.title',
        'override_value' => 'Tenant dashboard',
    ]);

    TenantTranslationOverride::query()->create([
        'locale' => 'ru',
        'translation_key' => 'admin.dashboard.title',
        'override_value' => 'Tenant dashboard ru',
    ]);

    TenantTranslationOverride::query()->create([
        'locale' => 'hy',
        'translation_key' => 'admin.dashboard.heading',
        'override_value' => 'Tenant heading',
    ]);

    expect(fn (): TenantTranslationOverride => TenantTranslationOverride::query()->create([
        'locale' => 'hy',
        'translation_key' => 'admin.dashboard.title',
        'override_value' => 'Duplicate tenant dashboard',
    ]))->toThrow(QueryException::class);
});
