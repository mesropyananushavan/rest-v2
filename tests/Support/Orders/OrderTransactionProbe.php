<?php

declare(strict_types=1);

namespace Tests\Support\Orders;

use App\Modules\Orders\Application\RunsOrderTransactions;

final class OrderTransactionProbe
{
    use RunsOrderTransactions {
        runOrderTransaction as public run;
    }
}
