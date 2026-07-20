<?php

declare(strict_types=1);

namespace App\Modules\Identity\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

interface Authorizer
{
    public function allows(Authenticatable $user, string $permission): bool;
}
