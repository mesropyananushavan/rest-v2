<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Directory;

use App\Modules\Identity\Contracts\UserDirectory;
use App\Modules\Identity\Infrastructure\Models\User;

final class EloquentUserDirectory implements UserDirectory
{
    public function findName(int $userId): ?string
    {
        return User::query()->find($userId)?->name;
    }
}
