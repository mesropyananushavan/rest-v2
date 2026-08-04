<?php

declare(strict_types=1);

namespace App\Modules\Orders\Contracts;

interface PayableOrderReader
{
    /**
     * Returns an unlocked payable-order snapshot for non-financial inspection.
     *
     * The returned snapshot can become stale immediately after it is read. Do
     * not use this method as the basis for payment capture or other financial
     * writes. Callers that write data based on payable invariants must open
     * their own database transaction first and call lockPayableForUpdate().
     */
    public function findPayable(int $orderId): PayableOrderSnapshot;

    /**
     * Locks the order row for writes that depend on payable invariants.
     *
     * An existing caller-owned database transaction is mandatory. The caller
     * must keep that transaction open through all dependent financial writes.
     * Payable invariants are rechecked after the row lock is obtained, and the
     * lock ends only when the caller's transaction commits or rolls back.
     */
    public function lockPayableForUpdate(int $orderId): PayableOrderSnapshot;
}
