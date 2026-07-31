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

    public static function waiterNotAssignable(): self
    {
        return new self('orders.waiter_not_assignable', 'The selected waiter cannot take orders in this branch.');
    }

    public static function menuItemNotFound(): self
    {
        return new self('orders.menu_item_not_found', 'The selected menu item is not available for sale in this branch.');
    }

    public static function currencyMismatch(): self
    {
        return new self('orders.currency_mismatch', 'The menu item currency does not match the order currency.');
    }

    public static function itemNotInOrder(): self
    {
        return new self('orders.item_not_in_order', 'The selected order item is not available in this order.');
    }

    public static function invalidQuantity(): self
    {
        return new self('orders.invalid_quantity', 'Order item quantity must be at least one.');
    }

    public static function subtableNotInOrder(): self
    {
        return new self('orders.subtable_not_in_order', 'The selected subtable does not belong to this order.');
    }

    public static function invalidOrderType(): self
    {
        return new self('orders.invalid_order_type', 'Unsupported order type.');
    }

    public static function itemMoveNoop(): self
    {
        return new self('orders.item_move_noop', 'The order item is already in the requested location.');
    }

    public static function orderMoveNoop(): self
    {
        return new self('orders.order_move_noop', 'The order is already assigned to the requested table.');
    }

    public static function orderBranchMismatch(): self
    {
        return new self('orders.order_branch_mismatch', 'Order items cannot be moved across branches.');
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }
}
