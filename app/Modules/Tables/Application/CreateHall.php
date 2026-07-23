<?php

declare(strict_types=1);

namespace App\Modules\Tables\Application;

use App\Modules\Tables\Domain\TablesDomainException;
use App\Modules\Tables\Infrastructure\Models\Hall;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Support\I18n\LocalizedText;
use Illuminate\Support\Facades\DB;

final class CreateHall
{
    use RecordsTableAction;

    public function __construct(
        private readonly BranchContext $branches,
    ) {}

    public function __invoke(LocalizedText $name, string $color, int $sortOrder = 0, bool $active = true): Hall
    {
        $startedAt = microtime(true);
        $branchId = $this->branches->id();

        if ($branchId === null) {
            $exception = TablesDomainException::branchContextRequired();
            $this->logDomainFailure('tables.halls.create', $exception, $startedAt);

            throw $exception;
        }

        $hall = DB::transaction(function () use ($active, $branchId, $color, $name, $sortOrder): Hall {
            $hall = Hall::query()->create([
                'branch_id' => $branchId,
                'translated_name' => $name->toArray(),
                'color' => $color,
                'sort_order' => $sortOrder,
                'active' => $active,
            ]);

            $this->auditTableMutation(
                'tables.hall.created',
                'tables_hall',
                (int) $hall->id,
                null,
                $this->hallAuditPayload($hall),
            );

            return $hall;
        });

        $this->logSuccess('tables.halls.create', $startedAt, [
            'branch_id' => $branchId,
            'hall_id' => (int) $hall->id,
        ]);

        return $hall;
    }
}
