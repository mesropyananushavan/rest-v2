<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application;

use Closure;
use Illuminate\Support\Facades\DB;

trait RunsCashboxTransactions
{
    private const int CASHBOX_CONCURRENCY_ATTEMPTS = 3;

    /**
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    protected function runCashboxTransaction(Closure $callback): mixed
    {
        return DB::transaction($callback, self::CASHBOX_CONCURRENCY_ATTEMPTS);
    }
}
