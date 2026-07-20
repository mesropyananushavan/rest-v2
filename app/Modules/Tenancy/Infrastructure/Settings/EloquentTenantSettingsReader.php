<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Infrastructure\Settings;

use App\Modules\Tenancy\Contracts\TenantSettingsReader;
use App\Modules\Tenancy\Infrastructure\Models\Tenant;

final class EloquentTenantSettingsReader implements TenantSettingsReader
{
    public function settingsFor(int $tenantId): ?array
    {
        $tenant = Tenant::query()->find($tenantId);

        if (! $tenant instanceof Tenant) {
            return null;
        }

        return [
            'default_locale' => (string) $tenant->default_locale,
            'currency' => (string) $tenant->currency,
        ];
    }
}
