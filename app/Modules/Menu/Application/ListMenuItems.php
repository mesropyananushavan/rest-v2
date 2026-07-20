<?php

declare(strict_types=1);

namespace App\Modules\Menu\Application;

use App\Modules\Menu\Domain\MenuDomainException;
use App\Modules\Menu\Infrastructure\Models\MenuItem;
use App\Modules\Tenancy\Contracts\BranchContext;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

final class ListMenuItems
{
    use RecordsMenuAction;

    public function __construct(
        private readonly BranchContext $branches,
    ) {}

    /**
     * @return EloquentCollection<int, MenuItem>
     */
    public function __invoke(?int $categoryId = null): EloquentCollection
    {
        $startedAt = microtime(true);
        $branchId = $this->branches->id();

        if ($branchId === null) {
            $exception = MenuDomainException::branchContextRequired();
            $this->logDomainFailure('menu.items.list', $exception, $startedAt);

            throw $exception;
        }

        $items = MenuItem::query()
            ->with('category')
            ->where('branch_id', $branchId)
            ->when($categoryId !== null, fn ($query) => $query->where('category_id', $categoryId))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $this->logSuccess('menu.items.list', $startedAt, [
            'branch_id' => $branchId,
            'category_id' => $categoryId,
            'item_count' => $items->count(),
        ]);

        return $items;
    }
}
