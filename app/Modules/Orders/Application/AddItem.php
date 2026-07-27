<?php

declare(strict_types=1);

namespace App\Modules\Orders\Application;

use App\Modules\Menu\Contracts\MenuCatalog;
use App\Modules\Orders\Domain\OrdersDomainException;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Orders\Infrastructure\Models\OrderItem;
use App\Modules\Orders\Infrastructure\Models\OrderSubtable;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Support\I18n\LocalizedText;
use App\Support\Money\Money;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

final class AddItem
{
    use LocksOrdersForUpdate;
    use RecomputesOrderTotals;
    use RecordsOrderAction;
    use RunsOrderTransactions;

    public function __construct(
        private readonly TenantResolver $tenants,
        private readonly BranchContext $branches,
        private readonly MenuCatalog $menu,
    ) {}

    public function __invoke(int $orderId, int $menuItemId, int $qty, ?int $subtableId = null): OrderItem
    {
        $startedAt = microtime(true);
        $this->tenantIdOrFail('orders.items.add', $startedAt, [
            'order_id' => $orderId,
            'menu_item_id' => $menuItemId,
        ]);
        $branchId = $this->branchIdOrFail('orders.items.add', $startedAt, [
            'order_id' => $orderId,
            'menu_item_id' => $menuItemId,
        ]);

        if ($qty < 1) {
            $exception = OrdersDomainException::invalidQuantity();
            $this->logDomainFailure('orders.items.add', $exception, $startedAt, [
                'branch_id' => $branchId,
                'order_id' => $orderId,
                'menu_item_id' => $menuItemId,
                'qty' => $qty,
            ]);

            throw $exception;
        }

        $item = $this->runOrderTransaction(function () use ($branchId, $menuItemId, $orderId, $qty, $startedAt, $subtableId): OrderItem {
            $order = $this->lockOpenOrderForUpdate($orderId, $branchId, 'orders.items.add', $startedAt, [
                'menu_item_id' => $menuItemId,
                'qty' => $qty,
                'subtable_id' => $subtableId,
            ]);

            if ($subtableId !== null) {
                $this->ensureSubtableBelongsToOrder($subtableId, $order, 'orders.items.add', $startedAt, [
                    'menu_item_id' => $menuItemId,
                    'qty' => $qty,
                ]);
            }

            $menuItem = $this->menu->findSellableInBranch($menuItemId, $branchId);

            if ($menuItem === null) {
                $exception = OrdersDomainException::menuItemNotFound();
                $this->logDomainFailure('orders.items.add', $exception, $startedAt, [
                    'branch_id' => $branchId,
                    'order_id' => $orderId,
                    'menu_item_id' => $menuItemId,
                ]);

                throw $exception;
            }

            if ($menuItem->price->currency !== (string) $order->currency) {
                $exception = OrdersDomainException::currencyMismatch();
                $this->logDomainFailure('orders.items.add', $exception, $startedAt, [
                    'branch_id' => $branchId,
                    'order_id' => $orderId,
                    'menu_item_id' => $menuItemId,
                    'order_currency' => (string) $order->currency,
                    'item_currency' => $menuItem->price->currency,
                ]);

                throw $exception;
            }

            $menuItemNameSnapshot = $menuItem->name->toArray();

            /** @var Collection<int, OrderItem> $candidateLines */
            $candidateLines = OrderItem::query()
                ->where('branch_id', $branchId)
                ->where('order_id', (int) $order->id)
                ->where('menu_item_id', $menuItem->id)
                ->where('unit_price_minor', $menuItem->price->minor)
                ->where('currency', $menuItem->price->currency)
                ->where('discount_minor', 0)
                ->where('subtable_id', $subtableId)
                ->lockForUpdate()
                ->get();

            $line = $this->matchingLineForSnapshot($candidateLines, $menuItemNameSnapshot);

            if ($line instanceof OrderItem) {
                $before = $this->orderItemAuditPayload($line);
                $line->update([
                    'qty' => (int) $line->qty + $qty,
                    'total_minor' => $this->lineTotal((int) $line->unit_price_minor, (int) $line->qty + $qty, (int) $line->discount_minor, (string) $line->currency)->minor,
                ]);
                $line->refresh();
            } else {
                $before = null;
                $line = OrderItem::query()->create([
                    'branch_id' => $branchId,
                    'order_id' => (int) $order->id,
                    'subtable_id' => $subtableId,
                    'menu_item_id' => $menuItem->id,
                    'menu_item_name_snapshot' => $menuItemNameSnapshot,
                    'qty' => $qty,
                    'unit_price_minor' => $menuItem->price->minor,
                    'discount_minor' => 0,
                    'total_minor' => $this->lineTotal($menuItem->price->minor, $qty, 0, $menuItem->price->currency)->minor,
                    'currency' => $menuItem->price->currency,
                    'seller_id' => $this->actingUserId(),
                    'preparation_status' => 'pending',
                ]);
            }

            $this->recomputeOrderTotals($order);

            $this->auditOrderMutation(
                'orders.item.added',
                'orders_item',
                (int) $line->id,
                $before,
                $this->orderItemAuditPayload($line),
            );

            return $line;
        });

        $this->logSuccess('orders.items.add', $startedAt, [
            'branch_id' => $branchId,
            'order_id' => $orderId,
            'order_item_id' => (int) $item->id,
            'menu_item_id' => $menuItemId,
            'qty' => $qty,
        ]);

        return $item;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function ensureSubtableBelongsToOrder(int $subtableId, Order $order, string $action, float $startedAt, array $context): void
    {
        $subtable = OrderSubtable::query()
            ->where('branch_id', (int) $order->branch_id)
            ->where('order_id', (int) $order->id)
            ->whereKey($subtableId)
            ->lockForUpdate()
            ->first();

        if ($subtable instanceof OrderSubtable) {
            return;
        }

        $exception = OrdersDomainException::subtableNotInOrder();
        $this->logDomainFailure($action, $exception, $startedAt, [
            'branch_id' => (int) $order->branch_id,
            'order_id' => (int) $order->id,
            'subtable_id' => $subtableId,
        ] + $context);

        throw $exception;
    }

    /**
     * @param  Collection<int, OrderItem>  $candidateLines
     * @param  array{hy: string, ru: string, en: string}  $snapshot
     */
    private function matchingLineForSnapshot(Collection $candidateLines, array $snapshot): ?OrderItem
    {
        foreach ($candidateLines as $candidateLine) {
            if ($this->normalizedMenuItemNameSnapshot($candidateLine->menu_item_name_snapshot) === $snapshot) {
                return $candidateLine;
            }
        }

        return null;
    }

    /**
     * @return array{hy: string, ru: string, en: string}|null
     */
    private function normalizedMenuItemNameSnapshot(mixed $snapshot): ?array
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

    private function lineTotal(int $unitPriceMinor, int $qty, int $discountMinor, string $currency): Money
    {
        if ($qty < 1 || $unitPriceMinor > intdiv(PHP_INT_MAX, $qty)) {
            throw OrdersDomainException::invalidQuantity();
        }

        return new Money(($unitPriceMinor * $qty) - $discountMinor, $currency);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function tenantIdOrFail(string $action, float $startedAt, array $context): int
    {
        $tenantId = $this->tenants->id();

        if ($tenantId !== null) {
            return $tenantId;
        }

        $exception = OrdersDomainException::tenantContextRequired();
        $this->logDomainFailure($action, $exception, $startedAt, $context);

        throw $exception;
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

    private function actingUserId(): ?int
    {
        $userId = Auth::id();

        return is_numeric($userId) ? (int) $userId : null;
    }
}
