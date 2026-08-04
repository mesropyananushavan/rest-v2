<?php

declare(strict_types=1);

namespace App\Modules\Orders\Contracts;

interface PayableOrderReader
{
    public function findPayable(int $orderId): PayableOrderSnapshot;

    /**
     * Locks the order row for a future financial write owned by the caller.
     *
     * Call this only inside the caller's database transaction. The reader does
     * not open its own transaction because that would release the lock before
     * payment rows and ledger entries can be written.
     */
    public function lockPayableForUpdate(int $orderId): PayableOrderSnapshot;
}
