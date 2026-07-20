<?php

namespace App\Modules\Tenancy\Contracts;

interface TenantResolver
{
    public function id(): ?int;

    public function set(?int $tenantId): void;

    public function clear(): void;
}
