<?php

declare(strict_types=1);

namespace App\Modules\Orders\Domain;

use RuntimeException;

final class OrdersDomainException extends RuntimeException
{
    private function __construct(
        private readonly string $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function tenantContextRequired(): self
    {
        return new self('orders.tenant_context_required', 'Order operations require a resolved tenant context.');
    }

    public static function branchContextRequired(): self
    {
        return new self('orders.branch_context_required', 'Order operations require a resolved branch context.');
    }

    public static function tableNotFound(): self
    {
        return new self('orders.table_not_found', 'The selected table is not available in the current branch.');
    }

    public static function tableAlreadyOpen(): self
    {
        return new self('orders.table_already_open', 'This table already has an open order.');
    }

    public static function orderNotOpen(): self
    {
        return new self('orders.order_not_open', 'Only open orders can be changed.');
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }
}
