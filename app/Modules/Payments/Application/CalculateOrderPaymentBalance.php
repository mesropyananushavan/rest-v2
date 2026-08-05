<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application;

use App\Modules\Orders\Contracts\PayableOrderSnapshot;
use App\Modules\Payments\Domain\PaymentsDomainException;
use App\Modules\Payments\Infrastructure\Models\PaymentAllocation;

final class CalculateOrderPaymentBalance
{
    public function remainingMinor(PayableOrderSnapshot $order, int $tenantId, int $branchId): int
    {
        /** @var int|string|null $paidMinor */
        $paidMinor = PaymentAllocation::query()
            ->join('payments as captured_payments', 'captured_payments.id', '=', 'payment_allocations.payment_id')
            ->where('payment_allocations.tenant_id', $tenantId)
            ->where('payment_allocations.branch_id', $branchId)
            ->where('payment_allocations.payable_type', 'order')
            ->where('payment_allocations.payable_id', $order->orderId)
            ->where('captured_payments.tenant_id', $tenantId)
            ->where('captured_payments.branch_id', $branchId)
            ->where('captured_payments.status', 'captured')
            ->sum('payment_allocations.amount_minor');

        $paidMinor = (int) $paidMinor;

        if ($paidMinor === $order->totalMinor) {
            throw PaymentsDomainException::orderAlreadyFullyPaid();
        }

        if ($paidMinor > $order->totalMinor) {
            throw PaymentsDomainException::orderOverAllocated();
        }

        return $order->totalMinor - $paidMinor;
    }
}
