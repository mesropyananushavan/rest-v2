<?php

declare(strict_types=1);

namespace App\Modules\Menu\Application;

use App\Modules\Menu\Domain\MenuDomainException;
use App\Modules\Menu\Infrastructure\Models\MenuItem;
use App\Modules\Tenancy\Contracts\BranchContext;

final class ForceDeleteMenuItem
{
    use RecordsMenuAction;

    public function __construct(
        private readonly BranchContext $branches,
    ) {}

    public function __invoke(int $itemId): void
    {
        $startedAt = microtime(true);
        $branchId = $this->branches->id();

        if ($branchId === null) {
            $exception = MenuDomainException::branchContextRequired();
            $this->logDomainFailure('menu.items.force_delete', $exception, $startedAt, [
                'item_id' => $itemId,
            ]);

            throw $exception;
        }

        MenuItem::onlyTrashed()
            ->where('branch_id', $branchId)
            ->findOrFail($itemId)
            ->forceDelete();

        $this->logSuccess('menu.items.force_delete', $startedAt, [
            'branch_id' => $branchId,
            'item_id' => $itemId,
        ]);
    }
}
