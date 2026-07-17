<?php

namespace App\Modules\Tenancy\Contracts;

interface TenantSettingsReader
{
    /**
     * @return array{default_locale: string, currency: string}|null
     */
    public function settingsFor(int $tenantId): ?array;
}
