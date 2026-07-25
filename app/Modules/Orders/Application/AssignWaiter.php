<?php

declare(strict_types=1);

namespace App\Modules\Orders\Application;

use App\Modules\Orders\Domain\OrdersDomainException;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Tenancy\Contracts\BranchContext;
use Illuminate\Support\Facades\DB;

final class AssignWaiter
{
    use RecordsOrderAction;

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

        $order = Order::query()
            ->where('branch_id', $branchId)
            ->findOrFail($orderId);

        if ($order->status !== 'open') {
            $exception = OrdersDomainException::orderNotOpen();
            $this->logDomainFailure('orders.assign_waiter', $exception, $startedAt, [
                'branch_id' => $branchId,
                'order_id' => $orderId,
                'status' => (string) $order->status,
            ]);

            throw $exception;
        }

        $before = $this->orderAuditPayload($order);

        DB::transaction(function () use ($before, $order, $waiterId): void {
            $order->update(['waiter_id' => $waiterId]);

            $this->auditOrderMutation(
                'orders.order.waiter_assigned',
                'orders_order',
                (int) $order->id,
                $before,
                $this->orderAuditPayload($order->refresh()),
            );
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
