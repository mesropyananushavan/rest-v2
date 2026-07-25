<?php

declare(strict_types=1);

namespace App\Modules\Orders\Application;

use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Orders\Infrastructure\Models\OrderItem;
use App\Support\Money\Money;

trait RecomputesOrderTotals
{
    private function recomputeOrderTotals(Order $order): Order
    {
        $subtotalMinor = (int) OrderItem::query()
            ->where('branch_id', (int) $order->branch_id)
            ->where('order_id', (int) $order->id)
            ->sum('total_minor');

        $subtotal = new Money($subtotalMinor, (string) $order->currency);
        $total = new Money($subtotal->minor - (int) $order->discount_minor, (string) $order->currency);

        $order->update([
            'subtotal_minor' => $subtotal->minor,
            'total_minor' => $total->minor,
        ]);

        return $order->refresh();
    }
}
