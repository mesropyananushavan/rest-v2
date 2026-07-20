<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Contracts;

interface TenantDirectory
{
    /**
     * @return list<int>
     */
    public function activeTenantIds(): array;
}
