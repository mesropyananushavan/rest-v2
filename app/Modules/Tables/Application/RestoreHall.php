<?php

declare(strict_types=1);

namespace App\Modules\Tables\Application;

use App\Modules\Tables\Domain\TablesDomainException;
use App\Modules\Tables\Infrastructure\Models\Hall;
use App\Modules\Tenancy\Contracts\BranchContext;
use Illuminate\Support\Facades\DB;

final class RestoreHall
{
    use RecordsTableAction;

    public function __construct(
        private readonly BranchContext $branches,
    ) {}

    public function __invoke(int $hallId): void
    {
        $startedAt = microtime(true);
        $branchId = $this->branches->id();

        if ($branchId === null) {
            $exception = TablesDomainException::branchContextRequired();
            $this->logDomainFailure('tables.halls.restore', $exception, $startedAt, [
                'hall_id' => $hallId,
            ]);

            throw $exception;
        }

        $hall = Hall::withTrashed()
            ->where('branch_id', $branchId)
            ->findOrFail($hallId);
        $before = $this->hallAuditPayload($hall);

        DB::transaction(function () use ($before, $hall, $hallId): void {
            $hall->forceFill([
                'deleted_at' => null,
                'updated_at' => now(),
            ])->save();

            $this->auditTableMutation(
                'tables.hall.restored',
                'tables_hall',
                $hallId,
                $before,
                $this->hallAuditPayload($hall->refresh()),
            );
        });

        $this->logSuccess('tables.halls.restore', $startedAt, [
            'branch_id' => $branchId,
            'hall_id' => $hallId,
        ]);
    }
}
