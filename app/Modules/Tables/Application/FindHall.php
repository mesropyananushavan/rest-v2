<?php

declare(strict_types=1);

namespace App\Modules\Tables\Application;

use App\Modules\Tables\Domain\TablesDomainException;
use App\Modules\Tables\Infrastructure\Models\Hall;
use App\Modules\Tenancy\Contracts\BranchContext;

final class FindHall
{
    use RecordsTableAction;

    public function __construct(
        private readonly BranchContext $branches,
    ) {}

    public function __invoke(int $hallId): Hall
    {
        $startedAt = microtime(true);
        $branchId = $this->branches->id();

        if ($branchId === null) {
            $exception = TablesDomainException::branchContextRequired();
            $this->logDomainFailure('tables.halls.find', $exception, $startedAt, [
                'hall_id' => $hallId,
            ]);

            throw $exception;
        }

        $hall = Hall::query()
            ->where('branch_id', $branchId)
            ->findOrFail($hallId);

        $this->logSuccess('tables.halls.find', $startedAt, [
            'branch_id' => $branchId,
            'hall_id' => $hallId,
        ]);

        return $hall;
    }
}
