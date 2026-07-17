<?php

namespace App\Modules\Identity\Infrastructure\Authorization;

use App\Modules\Identity\Contracts\PermissionCatalog;
use App\Modules\Identity\Infrastructure\Models\Permission;

final class EloquentPermissionCatalog implements PermissionCatalog
{
    public function allCodes(): array
    {
        $codes = Permission::query()
            ->orderBy('code')
            ->get(['code'])
            ->map(function (Permission $permission): string {
                $code = $permission->getAttribute('code');

                return is_string($code) ? $code : '';
            })
            ->values()
            ->all();

        /** @var list<string> $codes */
        return $codes;
    }
}
