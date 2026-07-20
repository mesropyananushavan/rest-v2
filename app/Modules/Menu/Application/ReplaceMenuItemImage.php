<?php

declare(strict_types=1);

namespace App\Modules\Menu\Application;

use App\Modules\Menu\Domain\MenuDomainException;
use App\Modules\Menu\Domain\MenuItemImageSlot;
use App\Modules\Menu\Infrastructure\Models\MenuItem;
use App\Modules\Menu\Infrastructure\Storage\MenuItemImageStorage;
use App\Modules\Tenancy\Contracts\BranchContext;
use Illuminate\Http\UploadedFile;
use Throwable;

final class ReplaceMenuItemImage
{
    use RecordsMenuAction;

    public function __construct(
        private readonly BranchContext $branches,
        private readonly MenuItemImageStorage $storage,
    ) {}

    public function __invoke(int $itemId, MenuItemImageSlot $slot, UploadedFile $file): MenuItem
    {
        $startedAt = microtime(true);
        $branchId = $this->branches->id();

        if ($branchId === null) {
            $exception = MenuDomainException::branchContextRequired();
            $this->logDomainFailure('menu.items.images.replace', $exception, $startedAt, [
                'item_id' => $itemId,
                'slot' => $slot->value,
            ]);

            throw $exception;
        }

        $item = MenuItem::query()
            ->where('branch_id', $branchId)
            ->findOrFail($itemId);
        $column = $slot->column();
        $oldImage = $this->imageMetadata($item, $column);
        $newImage = $this->storage->store($item, $slot, $file);

        try {
            $item->forceFill([$column => $newImage])->save();
        } catch (Throwable $exception) {
            $this->storage->delete($newImage);

            throw $exception;
        }

        $this->storage->delete($oldImage);

        $this->logSuccess('menu.items.images.replace', $startedAt, [
            'branch_id' => $branchId,
            'item_id' => $itemId,
            'slot' => $slot->value,
        ]);

        return $item->refresh();
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
