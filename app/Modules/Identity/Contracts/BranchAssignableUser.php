<?php

declare(strict_types=1);

namespace App\Modules\Identity\Contracts;

final readonly class BranchAssignableUser
{
    public function __construct(
        public int $id,
        public string $displayName,
    ) {}
}
