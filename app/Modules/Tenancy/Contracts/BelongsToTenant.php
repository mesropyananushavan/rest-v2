<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Contracts;

use Illuminate\Database\Eloquent\Model;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScoped);

        static::creating(function (Model $model): void {
            $tenantId = app(TenantResolver::class)->id();

            if ($tenantId !== null) {
                $model->setAttribute('tenant_id', $tenantId);
            }
        });
    }
}
