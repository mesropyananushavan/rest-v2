<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Infrastructure\Context;

use App\Modules\Tenancy\Contracts\BranchContext;
use Illuminate\Support\Facades\DB;

final class InMemoryBranchContext implements BranchContext
{
    private ?int $branchId = null;

    public function id(): ?int
    {
        return $this->branchId;
    }

    public function set(?int $branchId): void
    {
        $this->branchId = $branchId;
        $this->syncPostgresSetting('smartrest.branch_id', $branchId);
    }

    public function clear(): void
    {
        $this->set(null);
    }

    private function syncPostgresSetting(string $key, ?int $value): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('select set_config(?, ?, false)', [$key, $value === null ? '' : (string) $value]);
    }
}
