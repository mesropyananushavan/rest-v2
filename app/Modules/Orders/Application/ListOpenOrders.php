<?php

declare(strict_types=1);

namespace App\Modules\Orders\Application;

use App\Modules\Orders\Domain\OrdersDomainException;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Tenancy\Contracts\BranchContext;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListOpenOrders
{
    use RecordsOrderAction;

    private const DEFAULT_PER_PAGE = 25;

    private const MAX_PER_PAGE = 50;

    public function __construct(
        private readonly BranchContext $branches,
    ) {}

    /**
     * @return LengthAwarePaginator<int, Order>
     */
    public function __invoke(int $perPage = self::DEFAULT_PER_PAGE, int $page = 1): LengthAwarePaginator
    {
        $startedAt = microtime(true);
        $branchId = $this->branchIdOrFail('orders.open.list', $startedAt);
        $perPage = min(self::MAX_PER_PAGE, max(1, $perPage));
        $page = max(1, $page);

        /** @var LengthAwarePaginator<int, Order> $orders */
        $orders = Order::query()
            ->with('subtables')
            ->where('branch_id', $branchId)
            ->where('status', 'open')
            ->orderBy('opened_at')
            ->orderBy('id')
            ->paginate($perPage, ['*'], 'page', $page);

        $this->logSuccess('orders.open.list', $startedAt, [
            'branch_id' => $branchId,
            'order_count' => $orders->count(),
            'page' => $page,
            'per_page' => $perPage,
            'total' => $orders->total(),
        ]);

        return $orders;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function branchIdOrFail(string $action, float $startedAt, array $context = []): int
    {
        $branchId = $this->branches->id();

        if ($branchId !== null) {
            return $branchId;
        }

        $exception = OrdersDomainException::branchContextRequired();
        $this->logDomainFailure($action, $exception, $startedAt, $context);

        throw $exception;
    }
}
