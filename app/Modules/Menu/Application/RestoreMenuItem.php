<?php

declare(strict_types=1);

namespace App\Modules\Menu\Application;

use App\Modules\Menu\Domain\MenuDomainException;
use App\Modules\Menu\Infrastructure\Models\MenuCategory;
use App\Modules\Menu\Infrastructure\Models\MenuItem;
use App\Modules\Tenancy\Contracts\BranchContext;
use Illuminate\Support\Facades\DB;

final class RestoreMenuItem
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
            $this->logDomainFailure('menu.items.restore', $exception, $startedAt, [
                'item_id' => $itemId,
            ]);

            throw $exception;
        }

        $item = MenuItem::withTrashed()
            ->where('branch_id', $branchId)
            ->findOrFail($itemId);
        $before = $this->menuItemAuditPayload($item);

        $category = MenuCategory::withTrashed()->findOrFail((int) $item->category_id);

        if ($category->trashed()) {
            $exception = MenuDomainException::categoryArchived();
            $this->logDomainFailure('menu.items.restore', $exception, $startedAt, [
                'branch_id' => $branchId,
                'category_id' => (int) $category->id,
                'item_id' => $itemId,
            ]);

            throw $exception;
        }

        DB::transaction(function () use ($before, $item, $itemId): void {
            $item->forceFill([
                'deleted_at' => null,
                'archived_with_category_id' => null,
                'updated_at' => now(),
            ])->save();

            $this->auditMenuMutation(
                'menu.item.restored',
                'menu_item',
                $itemId,
                $before,
                $this->menuItemAuditPayload($item->refresh()),
            );
        });

        $this->logSuccess('menu.items.restore', $startedAt, [
            'branch_id' => $branchId,
            'item_id' => $itemId,
        ]);
    }
}
