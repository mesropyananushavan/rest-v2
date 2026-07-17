<?php

namespace App\Modules\Identity\Infrastructure\Authorization;

use App\Modules\Identity\Contracts\Authorizer;
use App\Modules\Identity\Infrastructure\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

final class EloquentAuthorizer implements Authorizer
{
    public function allows(Authenticatable $user, string $permission): bool
    {
        if (! $user instanceof User || ! $user->active) {
            return false;
        }

        return $user->role()
            ->whereHas('permissions', fn ($query) => $query->where('code', $permission))
            ->exists();
    }
}
