<?php

namespace App\Modules\Identity\Contracts;

interface UserDirectory
{
    public function findName(int $userId): ?string;
}
