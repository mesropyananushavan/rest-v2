<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Contracts;

interface TenantResolver
{
    public function id(): ?int;

    public function set(?int $tenantId): void;

    public function clear(): void;
}
