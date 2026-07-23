<?php

declare(strict_types=1);

namespace App\Modules\Menu\Application;

use App\Modules\Menu\Domain\MenuDomainException;
use App\Modules\Menu\Infrastructure\Models\MenuItem;
use App\Modules\Menu\Infrastructure\Storage\MenuItemImageStorage;
use App\Modules\Tenancy\Contracts\BranchContext;
use Illuminate\Support\Facades\DB;

final class ForceDeleteMenuItem
{
    use RecordsMenuAction;

    public function __construct(
        private readonly BranchContext $branches,
        private readonly MenuItemImageStorage $storage,
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

        $item = MenuItem::onlyTrashed()
            ->where('branch_id', $branchId)
            ->findOrFail($itemId);
        $before = $this->menuItemAuditPayload($item);

        $internalImage = $this->imageMetadata($item, 'internal_image');
        $publicImage = $this->imageMetadata($item, 'public_image');

        DB::transaction(function () use ($before, $item, $itemId): void {
            $item->forceDelete();

            $this->auditMenuMutation('menu.item.permanently_deleted', 'menu_item', $itemId, $before, [
                'deleted' => true,
            ]);
        });

        $this->storage->delete($internalImage);
        $this->storage->delete($publicImage);

        $this->logSuccess('menu.items.force_delete', $startedAt, [
            'branch_id' => $branchId,
            'item_id' => $itemId,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function imageMetadata(MenuItem $item, string $column): ?array
    {
        $metadata = $item->getAttribute($column);

        if (! is_array($metadata)) {
            return null;
        }

        /** @var array<string, mixed> $metadata */
        return $metadata;
    }
}
