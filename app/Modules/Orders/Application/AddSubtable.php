<?php

declare(strict_types=1);

namespace App\Modules\Orders\Application;

use App\Modules\Orders\Domain\OrdersDomainException;
use App\Modules\Orders\Infrastructure\Models\OrderSubtable;
use App\Modules\Tenancy\Contracts\BranchContext;

final class AddSubtable
{
    use LocksOrdersForUpdate;
    use RecordsOrderAction;
    use RunsOrderTransactions;

    public function __construct(
        private readonly BranchContext $branches,
    ) {}

    public function __invoke(int $orderId, string $name): OrderSubtable
    {
        $startedAt = microtime(true);
        $branchId = $this->branchIdOrFail('orders.subtables.add', $startedAt, [
            'order_id' => $orderId,
        ]);

        $subtable = $this->runOrderTransaction(function () use ($branchId, $name, $orderId, $startedAt): OrderSubtable {
            $order = $this->lockOpenOrderForUpdate($orderId, $branchId, 'orders.subtables.add', $startedAt, []);

            $subtable = OrderSubtable::query()->create([
                'branch_id' => $branchId,
                'order_id' => (int) $order->id,
                'name' => trim($name),
                'status' => 'open',
            ]);

            $this->auditOrderMutation(
                'orders.subtable.added',
                'orders_subtable',
                (int) $subtable->id,
                null,
                $this->orderSubtableAuditPayload($subtable),
            );

            return $subtable;
        });

        $this->logSuccess('orders.subtables.add', $startedAt, [
            'branch_id' => $branchId,
            'order_id' => $orderId,
            'subtable_id' => (int) $subtable->id,
        ]);

        return $subtable;
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
