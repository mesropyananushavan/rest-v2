<?php

declare(strict_types=1);

namespace App\Modules\Orders\Application;

use App\Modules\Orders\Domain\OrdersDomainException;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Orders\Infrastructure\Models\OrderItem;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;

final class RemoveItem
{
    use LocksOrdersForUpdate;
    use RecomputesOrderTotals;
    use RecordsOrderAction;
    use RunsOrderTransactions;

    public function __construct(
        private readonly TenantResolver $tenants,
        private readonly BranchContext $branches,
    ) {}

    public function __invoke(int $orderItemId): Order
    {
        $startedAt = microtime(true);
        $this->tenantIdOrFail('orders.items.remove', $startedAt, [
            'order_item_id' => $orderItemId,
        ]);
        $branchId = $this->branchIdOrFail('orders.items.remove', $startedAt, [
            'order_item_id' => $orderItemId,
        ]);

        $order = $this->runOrderTransaction(function () use ($branchId, $orderItemId, $startedAt): Order {
            $itemLookup = OrderItem::query()
                ->where('branch_id', $branchId)
                ->select(['id', 'order_id'])
                ->findOrFail($orderItemId);

            $order = $this->lockOpenOrderForUpdate((int) $itemLookup->order_id, $branchId, 'orders.items.remove', $startedAt, [
                'order_item_id' => $orderItemId,
            ]);

            $item = OrderItem::query()
                ->where('branch_id', $branchId)
                ->lockForUpdate()
                ->findOrFail($orderItemId);

            if ((int) $item->order_id !== (int) $order->id) {
                $exception = OrdersDomainException::itemNotInOrder();
                $this->logDomainFailure('orders.items.remove', $exception, $startedAt, [
                    'branch_id' => $branchId,
                    'order_id' => (int) $order->id,
                    'order_item_id' => $orderItemId,
                ]);

                throw $exception;
            }

            $before = $this->orderItemAuditPayload($item);
            $item->delete();

            $order = $this->recomputeOrderTotals($order);

            $this->auditOrderMutation(
                'orders.item.removed',
                'orders_item',
                $orderItemId,
                $before,
                null,
            );

            return $order;
        });

        $this->logSuccess('orders.items.remove', $startedAt, [
            'branch_id' => $branchId,
            'order_id' => (int) $order->id,
            'order_item_id' => $orderItemId,
        ]);

        return $order;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function tenantIdOrFail(string $action, float $startedAt, array $context): int
    {
        $tenantId = $this->tenants->id();

        if ($tenantId !== null) {
            return $tenantId;
        }

        $exception = OrdersDomainException::tenantContextRequired();
        $this->logDomainFailure($action, $exception, $startedAt, $context);

        throw $exception;
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
