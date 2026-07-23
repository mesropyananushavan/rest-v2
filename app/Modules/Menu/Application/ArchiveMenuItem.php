<?php

declare(strict_types=1);

namespace App\Modules\Menu\Application;

use App\Modules\Menu\Domain\MenuDomainException;
use App\Modules\Menu\Infrastructure\Models\MenuItem;
use App\Modules\Tenancy\Contracts\BranchContext;
use Illuminate\Support\Facades\DB;

final class ArchiveMenuItem
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
            $this->logDomainFailure('menu.items.archive', $exception, $startedAt, [
                'item_id' => $itemId,
            ]);

            throw $exception;
        }

        $item = MenuItem::query()
            ->where('branch_id', $branchId)
            ->findOrFail($itemId);
        $before = $this->menuItemAuditPayload($item);

        DB::transaction(function () use ($before, $item, $itemId): void {
            $item->forceFill(['archived_with_category_id' => null])->save();
            $item->delete();

            $this->auditMenuMutation(
                'menu.item.archived',
                'menu_item',
                $itemId,
                $before,
                $this->menuItemAuditPayload($item->refresh()),
            );
        });

        $this->logSuccess('menu.items.archive', $startedAt, [
            'branch_id' => $branchId,
            'item_id' => $itemId,
        ]);
    }
}
