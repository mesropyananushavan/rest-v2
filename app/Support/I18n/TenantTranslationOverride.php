<?php

declare(strict_types=1);

namespace App\Support\I18n;

use App\Modules\Tenancy\Contracts\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['tenant_id', 'locale', 'translation_key', 'override_value'])]
final class TenantTranslationOverride extends Model
{
    use BelongsToTenant;

    protected static function booted(): void
    {
        self::saved(function (TenantTranslationOverride $override): void {
            $tenantId = $override->getAttribute('tenant_id');
            $locale = $override->getAttribute('locale');

            if (is_int($tenantId) && is_string($locale)) {
                app(TenantTranslationOverrides::class)->invalidateTenantLocaleAfterWrite($tenantId, $locale);
            }
        });

        self::deleted(function (TenantTranslationOverride $override): void {
            $tenantId = $override->getAttribute('tenant_id');
            $locale = $override->getAttribute('locale');

            if (is_int($tenantId) && is_string($locale)) {
                app(TenantTranslationOverrides::class)->invalidateTenantLocaleAfterWrite($tenantId, $locale);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
        ];
    }
}
