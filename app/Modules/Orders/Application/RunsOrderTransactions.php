<?php

declare(strict_types=1);

namespace App\Modules\Orders\Application;

use Closure;
use Illuminate\Support\Facades\DB;

trait RunsOrderTransactions
{
    private const int ORDER_CONCURRENCY_ATTEMPTS = 3;

    /**
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    protected function runOrderTransaction(Closure $callback): mixed
    {
        return DB::transaction($callback, self::ORDER_CONCURRENCY_ATTEMPTS);
    }
}
