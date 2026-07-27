<?php

declare(strict_types=1);

namespace App\Modules\Orders\Application;

use App\Modules\Orders\Domain\OrdersDomainException;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Orders\Infrastructure\Models\OrderItem;
use App\Modules\Orders\Infrastructure\Models\OrderItemMove;
use App\Modules\Orders\Infrastructure\Models\OrderSubtable;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;
use Illuminate\Support\Facades\Auth;

final class MoveItem
{
    use LocksOrdersForUpdate;
    use RecomputesOrderTotals;
    use RecordsOrderAction;
    use RunsOrderTransactions;

    public function __construct(
        private readonly TenantResolver $tenants,
        private readonly BranchContext $branches,
    ) {}

    public function __invoke(
        int $orderItemId,
        ?int $targetOrderId = null,
        ?int $targetSubtableId = null,
        ?string $reason = null,
    ): OrderItem {
        $startedAt = microtime(true);
        $this->tenantIdOrFail('orders.items.move', $startedAt, [
            'order_item_id' => $orderItemId,
            'target_order_id' => $targetOrderId,
            'target_subtable_id' => $targetSubtableId,
        ]);
        $branchId = $this->branchIdOrFail('orders.items.move', $startedAt, [
            'order_item_id' => $orderItemId,
            'target_order_id' => $targetOrderId,
            'target_subtable_id' => $targetSubtableId,
        ]);

        $item = $this->runOrderTransaction(function () use ($branchId, $orderItemId, $reason, $startedAt, $targetOrderId, $targetSubtableId): OrderItem {
            $itemLookup = OrderItem::query()
                ->where('branch_id', $branchId)
                ->select(['id', 'order_id'])
                ->findOrFail($orderItemId);
            $sourceOrderId = (int) $itemLookup->order_id;
            $effectiveTargetOrderId = $targetOrderId ?? $sourceOrderId;

            if ($targetOrderId !== null && $targetOrderId !== $sourceOrderId) {
                $targetOrderLookup = Order::query()->findOrFail($targetOrderId);

                if ((int) $targetOrderLookup->branch_id !== $branchId) {
                    $exception = OrdersDomainException::orderBranchMismatch();
                    $this->logDomainFailure('orders.items.move', $exception, $startedAt, [
                        'branch_id' => $branchId,
                        'source_order_id' => $sourceOrderId,
                        'target_order_id' => $targetOrderId,
                        'target_branch_id' => (int) $targetOrderLookup->branch_id,
                        'order_item_id' => $orderItemId,
                        'target_subtable_id' => $targetSubtableId,
                    ]);

                    throw $exception;
                }

                $effectiveTargetOrderId = (int) $targetOrderLookup->id;
            }

            $orders = $this->lockOpenOrdersForUpdate(
                [$sourceOrderId, $effectiveTargetOrderId],
                $branchId,
                'orders.items.move',
                $startedAt,
                [
                    'order_item_id' => $orderItemId,
                    'target_order_id' => $targetOrderId,
                    'target_subtable_id' => $targetSubtableId,
                ],
            );
            $sourceOrder = $orders[$sourceOrderId];
            $targetOrder = $orders[$effectiveTargetOrderId];

            $item = OrderItem::query()
                ->where('branch_id', $branchId)
                ->lockForUpdate()
                ->findOrFail($orderItemId);

            if ((int) $item->order_id !== (int) $sourceOrder->id) {
                $exception = OrdersDomainException::itemNotInOrder();
                $this->logDomainFailure('orders.items.move', $exception, $startedAt, [
                    'branch_id' => $branchId,
                    'order_id' => (int) $sourceOrder->id,
                    'order_item_id' => $orderItemId,
                    'actual_order_id' => (int) $item->order_id,
                ]);

                throw $exception;
            }

            if ((string) $item->currency !== (string) $targetOrder->currency) {
                $exception = OrdersDomainException::currencyMismatch();
                $this->logDomainFailure('orders.items.move', $exception, $startedAt, [
                    'branch_id' => $branchId,
                    'order_item_id' => $orderItemId,
                    'source_order_id' => (int) $sourceOrder->id,
                    'target_order_id' => (int) $targetOrder->id,
                    'item_currency' => (string) $item->currency,
                    'target_currency' => (string) $targetOrder->currency,
                ]);

                throw $exception;
            }

            if ($targetSubtableId !== null) {
                $this->ensureSubtableBelongsToOrder($targetSubtableId, $targetOrder, 'orders.items.move', $startedAt, [
                    'order_item_id' => $orderItemId,
                    'source_order_id' => (int) $sourceOrder->id,
                ]);
            }

            $sourceSubtableId = $this->nullableInt($item->subtable_id);
            $sameOrder = (int) $sourceOrder->id === (int) $targetOrder->id;

            if ($sameOrder && $sourceSubtableId === $targetSubtableId) {
                $exception = OrdersDomainException::itemMoveNoop();
                $this->logDomainFailure('orders.items.move', $exception, $startedAt, [
                    'branch_id' => $branchId,
                    'order_item_id' => $orderItemId,
                    'order_id' => (int) $sourceOrder->id,
                    'subtable_id' => $targetSubtableId,
                ]);

                throw $exception;
            }

            $before = $this->orderItemAuditPayload($item);

            $item->update([
                'order_id' => (int) $targetOrder->id,
                'subtable_id' => $targetSubtableId,
            ]);
            $item->refresh();

            OrderItemMove::query()->create([
                'branch_id' => $branchId,
                'order_item_id' => $orderItemId,
                'source_order_id' => (int) $sourceOrder->id,
                'target_order_id' => (int) $targetOrder->id,
                'source_subtable_id' => $sourceSubtableId,
                'target_subtable_id' => $targetSubtableId,
                'actor_id' => $this->actingUserId(),
                'reason' => $reason,
            ]);

            $this->recomputeOrderTotals($sourceOrder);

            if (! $sameOrder) {
                $this->recomputeOrderTotals($targetOrder);
            }

            $this->auditOrderMutation(
                'orders.item.moved',
                'orders_item',
                (int) $item->id,
                $before,
                $this->orderItemAuditPayload($item),
            );

            return $item;
        });

        $this->logSuccess('orders.items.move', $startedAt, [
            'branch_id' => $branchId,
            'order_item_id' => (int) $item->id,
            'target_order_id' => (int) $item->order_id,
            'target_subtable_id' => $this->nullableInt($item->subtable_id),
        ]);

        return $item;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function ensureSubtableBelongsToOrder(int $subtableId, Order $order, string $action, float $startedAt, array $context): void
    {
        $subtable = OrderSubtable::query()
            ->where('branch_id', (int) $order->branch_id)
            ->where('order_id', (int) $order->id)
            ->whereKey($subtableId)
            ->lockForUpdate()
            ->first();

        if ($subtable instanceof OrderSubtable) {
            return;
        }

        $exception = OrdersDomainException::subtableNotInOrder();
        $this->logDomainFailure($action, $exception, $startedAt, [
            'branch_id' => (int) $order->branch_id,
            'order_id' => (int) $order->id,
            'subtable_id' => $subtableId,
        ] + $context);

        throw $exception;
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

    private function actingUserId(): ?int
    {
        $userId = Auth::id();

        return is_numeric($userId) ? (int) $userId : null;
    }
}
