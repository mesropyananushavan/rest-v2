<?php

namespace App\Modules\Identity\Contracts;

interface PermissionCatalog
{
    /**
     * @return list<string>
     */
    public function allCodes(): array;
}
