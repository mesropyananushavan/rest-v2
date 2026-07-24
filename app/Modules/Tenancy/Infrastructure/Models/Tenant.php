<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Infrastructure\Models;

use App\Support\I18n\TenantTranslationOverrides;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'slug', 'default_locale', 'currency', 'status'])]
final class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        self::created(function (Tenant $tenant): void {
            TenantTranslationOverrides::markTenantHasNoOverrides((int) $tenant->id);
        });
    }
}
