<?php

declare(strict_types=1);

namespace App\Modules\Identity\Contracts;

interface PermissionCatalog
{
    /**
     * @return list<string>
     */
    public function allCodes(): array;
}
