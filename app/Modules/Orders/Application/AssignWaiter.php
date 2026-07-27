<?php

declare(strict_types=1);

namespace App\Modules\Orders\Application;

use App\Modules\Orders\Domain\OrdersDomainException;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Tenancy\Contracts\BranchContext;

final class AssignWaiter
{
    use LocksOrdersForUpdate;
    use RecordsOrderAction;
    use RunsOrderTransactions;

    public function __construct(
        private readonly BranchContext $branches,
    ) {}

    public function __invoke(int $orderId, ?int $waiterId): Order
    {
        $startedAt = microtime(true);
        $branchId = $this->branchIdOrFail('orders.assign_waiter', $startedAt, [
            'order_id' => $orderId,
            'waiter_id' => $waiterId,
        ]);

        $order = $this->runOrderTransaction(function () use ($branchId, $orderId, $startedAt, $waiterId): Order {
            $order = $this->lockOpenOrderForUpdate($orderId, $branchId, 'orders.assign_waiter', $startedAt, [
                'waiter_id' => $waiterId,
            ]);
            $before = $this->orderAuditPayload($order);

            $order->update(['waiter_id' => $waiterId]);

            $this->auditOrderMutation(
                'orders.order.waiter_assigned',
                'orders_order',
                (int) $order->id,
                $before,
                $this->orderAuditPayload($order->refresh()),
            );

            return $order;
        });

        $this->logSuccess('orders.assign_waiter', $startedAt, [
            'branch_id' => $branchId,
            'order_id' => $orderId,
            'waiter_id' => $waiterId,
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
