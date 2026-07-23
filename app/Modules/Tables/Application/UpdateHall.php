<?php

declare(strict_types=1);

namespace App\Modules\Tables\Application;

use App\Modules\Tables\Domain\TablesDomainException;
use App\Modules\Tables\Infrastructure\Models\Hall;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Support\I18n\LocalizedText;
use Illuminate\Support\Facades\DB;

final class UpdateHall
{
    use RecordsTableAction;

    public function __construct(
        private readonly BranchContext $branches,
    ) {}

    public function __invoke(int $hallId, LocalizedText $name, string $color, int $sortOrder, bool $active): Hall
    {
        $startedAt = microtime(true);
        $branchId = $this->branches->id();

        if ($branchId === null) {
            $exception = TablesDomainException::branchContextRequired();
            $this->logDomainFailure('tables.halls.update', $exception, $startedAt, [
                'hall_id' => $hallId,
            ]);

            throw $exception;
        }

        $hall = Hall::query()
            ->where('branch_id', $branchId)
            ->findOrFail($hallId);
        $before = $this->hallAuditPayload($hall);

        DB::transaction(function () use ($active, $before, $color, $hall, $name, $sortOrder): void {
            $hall->update([
                'translated_name' => $name->toArray(),
                'color' => $color,
                'sort_order' => $sortOrder,
                'active' => $active,
            ]);

            $this->auditTableMutation(
                'tables.hall.updated',
                'tables_hall',
                (int) $hall->id,
                $before,
                $this->hallAuditPayload($hall->refresh()),
            );
        });

        $this->logSuccess('tables.halls.update', $startedAt, [
            'branch_id' => $branchId,
            'hall_id' => (int) $hall->id,
        ]);

        return $hall;
    }
}
