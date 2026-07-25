<?php

declare(strict_types=1);

namespace App\Modules\Orders\Application;

use App\Modules\Orders\Domain\OrdersDomainException;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Tenancy\Contracts\BranchContext;

final class FindOrder
{
    use RecordsOrderAction;

    public function __construct(
        private readonly BranchContext $branches,
    ) {}

    public function __invoke(int $orderId): Order
    {
        $startedAt = microtime(true);
        $branchId = $this->branchIdOrFail('orders.find', $startedAt, [
            'order_id' => $orderId,
        ]);

        $order = Order::query()
            ->with('subtables')
            ->where('branch_id', $branchId)
            ->findOrFail($orderId);

        $this->logSuccess('orders.find', $startedAt, [
            'branch_id' => $branchId,
            'order_id' => $orderId,
            'subtable_count' => $order->subtables->count(),
        ]);

        return $order;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function branchIdOrFail(string $action, float $startedAt, array $context): int
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
