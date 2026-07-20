<?php

namespace App\Modules\Tenancy\Contracts;

interface BranchContext
{
    public function id(): ?int;

    public function set(?int $branchId): void;

    public function clear(): void;
}
