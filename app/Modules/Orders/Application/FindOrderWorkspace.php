<?php

declare(strict_types=1);

namespace App\Modules\Orders\Application;

use App\Modules\Orders\Domain\OrdersDomainException;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Orders\Infrastructure\Models\OrderItem;
use App\Modules\Orders\Infrastructure\Models\OrderSubtable;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Support\I18n\LocalizedText;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;
use UnexpectedValueException;

final class FindOrderWorkspace
{
    use RecordsOrderAction;

    public function __construct(
        private readonly BranchContext $branches,
    ) {}

    public function __invoke(int $orderId): OrderWorkspace
    {
        $startedAt = microtime(true);
        $branchId = $this->branchIdOrFail('orders.workspace.find', $startedAt, [
            'order_id' => $orderId,
        ]);

        $order = Order::query()
            ->where('branch_id', $branchId)
            ->where('status', 'open')
            ->where('type', 'dine_in')
            ->whereNotNull('table_id')
            ->findOrFail($orderId, [
                'id',
                'branch_id',
                'type',
                'status',
                'table_id',
                'waiter_id',
                'opened_at',
                'client_count',
                'comment',
                'subtotal_minor',
                'discount_minor',
                'total_minor',
                'currency',
            ]);

        /** @var Collection<int, OrderSubtable> $subtables */
        $subtables = OrderSubtable::query()
            ->where('branch_id', $branchId)
            ->where('order_id', (int) $order->id)
            ->orderBy('id')
            ->get(['id', 'name']);

        /** @var Collection<int, OrderItem> $items */
        $items = OrderItem::query()
            ->where('branch_id', $branchId)
            ->where('order_id', (int) $order->id)
            ->orderBy('id')
            ->get([
                'id',
                'subtable_id',
                'menu_item_name_snapshot',
                'qty',
                'unit_price_minor',
                'discount_minor',
                'total_minor',
                'currency',
            ]);

        $workspace = new OrderWorkspace(
            id: (int) $order->id,
            type: (string) $order->type,
            status: (string) $order->status,
            tableId: (int) $order->table_id,
            assignedWaiterId: $this->nullableInt($order->waiter_id),
            openedAt: $this->openedAt($order),
            clientCount: (int) $order->client_count,
            comment: $order->comment === null ? null : (string) $order->comment,
            subtotalMinor: (int) $order->subtotal_minor,
            discountMinor: (int) $order->discount_minor,
            totalMinor: (int) $order->total_minor,
            currency: (string) $order->currency,
            subtables: array_values($subtables
                ->map(fn (OrderSubtable $subtable): OrderWorkspaceSubtable => new OrderWorkspaceSubtable(
                    id: (int) $subtable->id,
                    name: (string) $subtable->name,
                ))
                ->all()),
            items: array_values($items
                ->map(fn (OrderItem $item): OrderWorkspaceItem => new OrderWorkspaceItem(
                    id: (int) $item->id,
                    subtableId: $this->nullableInt($item->subtable_id),
                    nameSnapshot: $this->normalizedNameSnapshot($item->menu_item_name_snapshot),
                    qty: (int) $item->qty,
                    unitPriceMinor: (int) $item->unit_price_minor,
                    discountMinor: (int) $item->discount_minor,
                    totalMinor: (int) $item->total_minor,
                    currency: (string) $item->currency,
                ))
                ->all()),
        );

        $this->logSuccess('orders.workspace.find', $startedAt, [
            'branch_id' => $branchId,
            'order_id' => $orderId,
            'subtable_count' => count($workspace->subtables),
            'item_count' => count($workspace->items),
        ]);

        return $workspace;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function branchIdOrFail(string $action, float $startedAt, array $context): int
    {
        $branchId = $this->branches->id();

        if ($branchId !== null) {
            return $branchId;
        }

        $exception = OrdersDomainException::branchContextRequired();
        $this->logDomainFailure($action, $exception, $startedAt, $context);

        throw $exception;
    }

    private function openedAt(Order $order): DateTimeInterface
    {
        $openedAt = $order->getAttribute('opened_at');

        if ($openedAt instanceof DateTimeInterface) {
            return $openedAt;
        }

        if (is_string($openedAt) && $openedAt !== '') {
            return CarbonImmutable::parse($openedAt);
        }

        throw new UnexpectedValueException('Order opened_at attribute is not hydrated.');
    }

    /**
     * @return array{hy: string, ru: string, en: string}|null
     */
    private function normalizedNameSnapshot(mixed $snapshot): ?array
    {
        if (! is_array($snapshot)) {
            return null;
        }

        try {
            /** @var array<string, mixed> $translations */
            $translations = $snapshot;

            return LocalizedText::fromArray($translations)->toArray();
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    private function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
