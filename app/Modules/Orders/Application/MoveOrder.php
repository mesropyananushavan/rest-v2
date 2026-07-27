<?php

declare(strict_types=1);

namespace App\Modules\Orders\Application;

use App\Modules\Orders\Domain\OrdersDomainException;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Orders\Infrastructure\Models\OrderMove;
use App\Modules\Tables\Contracts\TableDirectory;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;

final class MoveOrder
{
    use LocksOrdersForUpdate;
    use RecordsOrderAction;
    use RunsOrderTransactions;

    public function __construct(
        private readonly TenantResolver $tenants,
        private readonly BranchContext $branches,
        private readonly TableDirectory $tables,
    ) {}

    public function __invoke(int $orderId, int $targetTableId, ?string $reason = null): Order
    {
        $startedAt = microtime(true);
        $this->tenantIdOrFail('orders.order.move', $startedAt, [
            'order_id' => $orderId,
            'target_table_id' => $targetTableId,
        ]);
        $branchId = $this->branchIdOrFail('orders.order.move', $startedAt, [
            'order_id' => $orderId,
            'target_table_id' => $targetTableId,
        ]);

        try {
            $order = $this->runOrderTransaction(function () use ($branchId, $orderId, $reason, $startedAt, $targetTableId): Order {
                $table = $this->tables->findActiveInBranch($targetTableId, $branchId);

                if ($table === null) {
                    $exception = OrdersDomainException::tableNotFound();
                    $this->logDomainFailure('orders.order.move', $exception, $startedAt, [
                        'branch_id' => $branchId,
                        'order_id' => $orderId,
                        'target_table_id' => $targetTableId,
                    ]);

                    throw $exception;
                }

                $targetOccupantOrderId = $this->targetOccupantOrderId($targetTableId, $branchId, $orderId);
                $orders = $this->lockOrdersForUpdate(
                    array_filter(
                        [$orderId, $targetOccupantOrderId],
                        static fn (?int $id): bool => $id !== null,
                    ),
                    $branchId,
                );
                $order = $orders[$orderId];

                $this->ensureOrderOpen($order, 'orders.order.move', $startedAt, [
                    'target_table_id' => $targetTableId,
                ]);

                if ($order->type !== 'dine_in') {
                    $exception = OrdersDomainException::invalidOrderType();
                    $this->logDomainFailure('orders.order.move', $exception, $startedAt, [
                        'branch_id' => $branchId,
                        'order_id' => $orderId,
                        'type' => (string) $order->type,
                        'target_table_id' => $targetTableId,
                    ]);

                    throw $exception;
                }

                $sourceTableId = $this->nullableInt($order->table_id);

                if ($sourceTableId === $targetTableId) {
                    $exception = OrdersDomainException::orderMoveNoop();
                    $this->logDomainFailure('orders.order.move', $exception, $startedAt, [
                        'branch_id' => $branchId,
                        'order_id' => $orderId,
                        'table_id' => $targetTableId,
                    ]);

                    throw $exception;
                }

                $targetOccupant = $targetOccupantOrderId !== null ? ($orders[$targetOccupantOrderId] ?? null) : null;

                if ($targetOccupant instanceof Order
                    && (int) $targetOccupant->id !== (int) $order->id
                    && (int) $targetOccupant->table_id === $targetTableId
                    && $targetOccupant->type === 'dine_in'
                    && $targetOccupant->status === 'open') {
                    $exception = OrdersDomainException::tableAlreadyOpen();
                    $this->logDomainFailure('orders.order.move', $exception, $startedAt, [
                        'branch_id' => $branchId,
                        'order_id' => $orderId,
                        'target_table_id' => $targetTableId,
                    ]);

                    throw $exception;
                }

                $before = $this->orderAuditPayload($order);

                $order->update([
                    'table_id' => $targetTableId,
                ]);
                $order->refresh();

                OrderMove::query()->create([
                    'branch_id' => $branchId,
                    'order_id' => $orderId,
                    'source_table_id' => $sourceTableId,
                    'target_table_id' => $targetTableId,
                    'actor_id' => $this->actingUserId(),
                    'reason' => $reason,
                ]);

                $this->auditOrderMutation(
                    'orders.order.moved',
                    'orders_order',
                    (int) $order->id,
                    $before,
                    $this->orderAuditPayload($order),
                );

                return $order;
            });
        } catch (QueryException $exception) {
            if (! $this->isOpenOrderUniqueViolation($exception)) {
                throw $exception;
            }

            $domainException = OrdersDomainException::tableAlreadyOpen();
            $this->logDomainFailure('orders.order.move', $domainException, $startedAt, [
                'branch_id' => $branchId,
                'order_id' => $orderId,
                'target_table_id' => $targetTableId,
            ]);

            throw $domainException;
        }

        $this->logSuccess('orders.order.move', $startedAt, [
            'branch_id' => $branchId,
            'order_id' => (int) $order->id,
            'target_table_id' => $targetTableId,
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

    private function actingUserId(): ?int
    {
        $userId = Auth::id();

        return is_numeric($userId) ? (int) $userId : null;
    }

    private function isOpenOrderUniqueViolation(QueryException $exception): bool
    {
        return (string) $exception->getCode() === '23505'
            && str_contains($exception->getMessage(), 'orders_one_open_dine_in_per_table_idx');
    }

    private function targetOccupantOrderId(int $targetTableId, int $branchId, int $movingOrderId): ?int
    {
        $id = Order::query()
            ->where('branch_id', $branchId)
            ->where('table_id', $targetTableId)
            ->where('type', 'dine_in')
            ->where('status', 'open')
            ->where('id', '<>', $movingOrderId)
            ->orderBy('id')
            ->value('id');

        return is_numeric($id) ? (int) $id : null;
    }
}
