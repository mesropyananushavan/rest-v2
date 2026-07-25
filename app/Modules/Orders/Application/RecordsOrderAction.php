<?php

declare(strict_types=1);

namespace App\Modules\Orders\Application;

use App\Modules\Orders\Domain\OrdersDomainException;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Orders\Infrastructure\Models\OrderItem;
use App\Modules\Orders\Infrastructure\Models\OrderSubtable;
use App\Support\Audit\AuditRecorder;
use App\Support\Logging\LogContext;
use App\Support\Logging\Redactor;
use DateTimeInterface;
use Illuminate\Support\Facades\Log;

trait RecordsOrderAction
{
    /**
     * @param  array<string, mixed>  $context
     */
    private function logSuccess(string $action, float $startedAt, array $context = []): void
    {
        LogContext::refreshRuntimeContext('orders');

        Log::info('action performed', Redactor::context([
            'action' => $action,
            'duration_ms' => $this->durationMs($startedAt),
        ] + $context));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function logDomainFailure(string $action, OrdersDomainException $exception, float $startedAt, array $context = []): void
    {
        LogContext::refreshRuntimeContext('orders');

        Log::warning('action failed', Redactor::context([
            'action' => $action,
            'error_code' => $exception->errorCode(),
            'duration_ms' => $this->durationMs($startedAt),
        ] + $context));
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    private function auditOrderMutation(string $action, string $targetType, int $targetId, ?array $before, ?array $after): void
    {
        LogContext::refreshRuntimeContext('orders');

        app(AuditRecorder::class)->record($action, $targetType, $targetId, $before, $after);
    }

    /**
     * @return array<string, mixed>
     */
    private function orderAuditPayload(Order $order): array
    {
        return [
            'id' => (int) $order->id,
            'branch_id' => (int) $order->branch_id,
            'type' => (string) $order->type,
            'status' => (string) $order->status,
            'table_id' => $this->nullableInt($order->table_id),
            'customer_id' => $this->nullableInt($order->customer_id),
            'waiter_id' => $this->nullableInt($order->waiter_id),
            'cashier_id' => $this->nullableInt($order->cashier_id),
            'opened_at' => $this->dateAuditValue($order->opened_at),
            'closed_at' => $this->dateAuditValue($order->closed_at),
            'client_count' => (int) $order->client_count,
            'comment' => $order->comment === null ? null : (string) $order->comment,
            'subtotal_minor' => (int) $order->subtotal_minor,
            'discount_minor' => (int) $order->discount_minor,
            'total_minor' => (int) $order->total_minor,
            'currency' => (string) $order->currency,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function orderSubtableAuditPayload(OrderSubtable $subtable): array
    {
        return [
            'id' => (int) $subtable->id,
            'branch_id' => (int) $subtable->branch_id,
            'order_id' => (int) $subtable->order_id,
            'name' => (string) $subtable->name,
            'status' => (string) $subtable->status,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function orderItemAuditPayload(OrderItem $item): array
    {
        return [
            'id' => (int) $item->id,
            'branch_id' => (int) $item->branch_id,
            'order_id' => (int) $item->order_id,
            'subtable_id' => $this->nullableInt($item->subtable_id),
            'menu_item_id' => (int) $item->menu_item_id,
            'qty' => (int) $item->qty,
            'unit_price_minor' => (int) $item->unit_price_minor,
            'discount_minor' => (int) $item->discount_minor,
            'total_minor' => (int) $item->total_minor,
            'currency' => (string) $item->currency,
            'seller_id' => $this->nullableInt($item->seller_id),
            'preparation_status' => (string) $item->preparation_status,
        ];
    }

    private function durationMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }

    private function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function dateAuditValue(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }

        return is_string($value) && $value !== '' ? $value : null;
    }
}
