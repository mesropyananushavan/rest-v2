<?php

declare(strict_types=1);

namespace App\Modules\Orders\Application;

use App\Modules\Orders\Domain\OrdersDomainException;
use App\Modules\Orders\Infrastructure\Models\Order;

trait LocksOrdersForUpdate
{
    /**
     * @param  array<string, mixed>  $context
     */
    protected function lockOpenOrderForUpdate(int $orderId, int $branchId, string $action, float $startedAt, array $context): Order
    {
        $order = $this->lockOrderForUpdate($orderId, $branchId);

        $this->ensureOrderOpen($order, $action, $startedAt, $context);

        return $order;
    }

    /**
     * @param  list<int>  $orderIds
     * @param  array<string, mixed>  $context
     * @return array<int, Order>
     */
    protected function lockOpenOrdersForUpdate(array $orderIds, int $branchId, string $action, float $startedAt, array $context): array
    {
        $orders = $this->lockOrdersForUpdate($orderIds, $branchId);

        foreach ($orders as $order) {
            $this->ensureOrderOpen($order, $action, $startedAt, $context);
        }

        return $orders;
    }

    /**
     * @param  list<int>  $orderIds
     * @return array<int, Order>
     */
    protected function lockOrdersForUpdate(array $orderIds, int $branchId): array
    {
        $ids = array_values(array_unique(array_map(
            static fn (int $orderId): int => $orderId,
            array_filter($orderIds, static fn (int $orderId): bool => $orderId > 0),
        )));
        sort($ids, SORT_NUMERIC);

        $orders = [];

        foreach ($ids as $orderId) {
            $orders[$orderId] = $this->lockOrderForUpdate($orderId, $branchId);
        }

        return $orders;
    }

    protected function lockOrderForUpdate(int $orderId, int $branchId): Order
    {
        return Order::query()
            ->where('branch_id', $branchId)
            ->lockForUpdate()
            ->findOrFail($orderId);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function ensureOrderOpen(Order $order, string $action, float $startedAt, array $context): void
    {
        if ($order->status === 'open') {
            return;
        }

        $exception = OrdersDomainException::orderNotOpen();
        $this->logDomainFailure($action, $exception, $startedAt, [
            'branch_id' => (int) $order->branch_id,
            'order_id' => (int) $order->id,
            'status' => (string) $order->status,
        ] + $context);

        throw $exception;
    }
}
