<?php

declare(strict_types=1);

namespace App\Modules\Orders\Application;

use App\Modules\Orders\Contracts\PayableOrderReader;
use App\Modules\Orders\Contracts\PayableOrderSnapshot;
use App\Modules\Orders\Domain\OrdersDomainException;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;
use Illuminate\Support\Facades\DB;

final class ReadPayableOrder implements PayableOrderReader
{
    use RecordsOrderAction;

    public function __construct(
        private readonly TenantResolver $tenants,
        private readonly BranchContext $branches,
    ) {}

    public function findPayable(int $orderId): PayableOrderSnapshot
    {
        $startedAt = microtime(true);
        $tenantId = $this->tenantIdOrFail('orders.payable.find', $startedAt, $orderId);
        $branchId = $this->branchIdOrFail('orders.payable.find', $startedAt, $orderId);

        $order = Order::query()
            ->where('branch_id', $branchId)
            ->findOrFail($orderId);

        $snapshot = $this->payableSnapshotOrFail($order, 'orders.payable.find', $startedAt);

        $this->logSuccess('orders.payable.find', $startedAt, [
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
            'order_id' => $orderId,
            'total_minor' => $snapshot->totalMinor,
            'currency' => $snapshot->currency,
        ]);

        return $snapshot;
    }

    public function lockPayableForUpdate(int $orderId): PayableOrderSnapshot
    {
        $startedAt = microtime(true);
        $tenantId = $this->tenantIdOrFail('orders.payable.lock', $startedAt, $orderId);
        $branchId = $this->branchIdOrFail('orders.payable.lock', $startedAt, $orderId);

        if (DB::connection()->transactionLevel() < 1) {
            $exception = OrdersDomainException::payableLockRequiresTransaction();
            $this->logDomainFailure('orders.payable.lock', $exception, $startedAt, [
                'tenant_id' => $tenantId,
                'branch_id' => $branchId,
                'order_id' => $orderId,
            ]);

            throw $exception;
        }

        $order = Order::query()
            ->where('branch_id', $branchId)
            ->lockForUpdate()
            ->findOrFail($orderId);

        $snapshot = $this->payableSnapshotOrFail($order, 'orders.payable.lock', $startedAt);

        $this->logSuccess('orders.payable.lock', $startedAt, [
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
            'order_id' => $orderId,
            'total_minor' => $snapshot->totalMinor,
            'currency' => $snapshot->currency,
        ]);

        return $snapshot;
    }

    private function payableSnapshotOrFail(Order $order, string $action, float $startedAt): PayableOrderSnapshot
    {
        if ($order->status !== 'open') {
            $exception = OrdersDomainException::orderNotOpen();
            $this->logDomainFailure($action, $exception, $startedAt, [
                'branch_id' => (int) $order->branch_id,
                'order_id' => (int) $order->id,
                'status' => (string) $order->status,
            ]);

            throw $exception;
        }

        if ((int) $order->total_minor <= 0) {
            $exception = OrdersDomainException::orderNotPayable();
            $this->logDomainFailure($action, $exception, $startedAt, [
                'branch_id' => (int) $order->branch_id,
                'order_id' => (int) $order->id,
                'total_minor' => (int) $order->total_minor,
            ]);

            throw $exception;
        }

        return new PayableOrderSnapshot(
            orderId: (int) $order->id,
            tenantId: (int) $order->tenant_id,
            branchId: (int) $order->branch_id,
            status: (string) $order->status,
            currency: (string) $order->currency,
            totalMinor: (int) $order->total_minor,
        );
    }

    private function tenantIdOrFail(string $action, float $startedAt, int $orderId): int
    {
        $tenantId = $this->tenants->id();

        if ($tenantId !== null) {
            return $tenantId;
        }

        $exception = OrdersDomainException::tenantContextRequired();
        $this->logDomainFailure($action, $exception, $startedAt, ['order_id' => $orderId]);

        throw $exception;
    }

    private function branchIdOrFail(string $action, float $startedAt, int $orderId): int
    {
        $branchId = $this->branches->id();

        if ($branchId !== null) {
            return $branchId;
        }

        $exception = OrdersDomainException::branchContextRequired();
        $this->logDomainFailure($action, $exception, $startedAt, ['order_id' => $orderId]);

        throw $exception;
    }
}
