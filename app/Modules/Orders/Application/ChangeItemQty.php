<?php

declare(strict_types=1);

namespace App\Modules\Orders\Application;

use App\Modules\Orders\Domain\OrdersDomainException;
use App\Modules\Orders\Infrastructure\Models\OrderItem;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Support\Money\Money;

final class ChangeItemQty
{
    use LocksOrdersForUpdate;
    use RecomputesOrderTotals;
    use RecordsOrderAction;
    use RunsOrderTransactions;

    public function __construct(
        private readonly TenantResolver $tenants,
        private readonly BranchContext $branches,
    ) {}

    public function __invoke(int $orderItemId, int $qty): OrderItem
    {
        $startedAt = microtime(true);
        $this->tenantIdOrFail('orders.items.qty.change', $startedAt, [
            'order_item_id' => $orderItemId,
        ]);
        $branchId = $this->branchIdOrFail('orders.items.qty.change', $startedAt, [
            'order_item_id' => $orderItemId,
        ]);

        if ($qty < 1) {
            $exception = OrdersDomainException::invalidQuantity();
            $this->logDomainFailure('orders.items.qty.change', $exception, $startedAt, [
                'branch_id' => $branchId,
                'order_item_id' => $orderItemId,
                'qty' => $qty,
            ]);

            throw $exception;
        }

        $item = $this->runOrderTransaction(function () use ($branchId, $orderItemId, $qty, $startedAt): OrderItem {
            $itemLookup = OrderItem::query()
                ->where('branch_id', $branchId)
                ->select(['id', 'order_id'])
                ->findOrFail($orderItemId);

            $order = $this->lockOpenOrderForUpdate((int) $itemLookup->order_id, $branchId, 'orders.items.qty.change', $startedAt, [
                'order_item_id' => $orderItemId,
                'qty' => $qty,
            ]);

            $item = OrderItem::query()
                ->where('branch_id', $branchId)
                ->lockForUpdate()
                ->findOrFail($orderItemId);

            if ((int) $item->order_id !== (int) $order->id) {
                $exception = OrdersDomainException::itemNotInOrder();
                $this->logDomainFailure('orders.items.qty.change', $exception, $startedAt, [
                    'branch_id' => $branchId,
                    'order_id' => (int) $order->id,
                    'order_item_id' => $orderItemId,
                ]);

                throw $exception;
            }

            $before = $this->orderItemAuditPayload($item);
            $item->update([
                'qty' => $qty,
                'total_minor' => $this->lineTotal((int) $item->unit_price_minor, $qty, (int) $item->discount_minor, (string) $item->currency)->minor,
            ]);
            $item->refresh();

            $this->recomputeOrderTotals($order);

            $this->auditOrderMutation(
                'orders.item.qty_changed',
                'orders_item',
                (int) $item->id,
                $before,
                $this->orderItemAuditPayload($item),
            );

            return $item;
        });

        $this->logSuccess('orders.items.qty.change', $startedAt, [
            'branch_id' => $branchId,
            'order_id' => (int) $item->order_id,
            'order_item_id' => (int) $item->id,
            'qty' => $qty,
        ]);

        return $item;
    }

    private function lineTotal(int $unitPriceMinor, int $qty, int $discountMinor, string $currency): Money
    {
        if ($qty < 1 || $unitPriceMinor > intdiv(PHP_INT_MAX, $qty)) {
            throw OrdersDomainException::invalidQuantity();
        }

        return new Money(($unitPriceMinor * $qty) - $discountMinor, $currency);
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
