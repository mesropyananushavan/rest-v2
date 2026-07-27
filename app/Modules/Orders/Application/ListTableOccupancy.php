<?php

declare(strict_types=1);

namespace App\Modules\Orders\Application;

use App\Modules\Orders\Domain\OrdersDomainException;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Tenancy\Contracts\BranchContext;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
use UnexpectedValueException;

final class ListTableOccupancy
{
    use RecordsOrderAction;

    public function __construct(
        private readonly BranchContext $branches,
    ) {}

    /**
     * @return array<int, TableOccupancy>
     */
    public function __invoke(): array
    {
        $startedAt = microtime(true);
        $branchId = $this->branchIdOrFail('orders.occupancy.list', $startedAt);

        /** @var Collection<int, Order> $orders */
        $orders = Order::query()
            ->where('branch_id', $branchId)
            ->where('status', 'open')
            ->where('type', 'dine_in')
            ->whereNotNull('table_id')
            ->orderBy('table_id')
            ->orderBy('id')
            ->get([
                'id',
                'table_id',
                'opened_at',
                'client_count',
                'total_minor',
                'currency',
                'waiter_id',
            ]);

        $occupancy = [];

        foreach ($orders as $order) {
            $openedAt = $this->openedAt($order);

            $tableId = (int) $order->table_id;
            $occupancy[$tableId] = new TableOccupancy(
                tableId: $tableId,
                orderId: (int) $order->id,
                openedAt: $openedAt,
                clientCount: (int) $order->client_count,
                totalMinor: (int) $order->total_minor,
                currency: (string) $order->currency,
                waiterId: $this->nullableInt($order->waiter_id),
            );
        }

        $this->logSuccess('orders.occupancy.list', $startedAt, [
            'branch_id' => $branchId,
            'occupied_table_count' => count($occupancy),
        ]);

        return $occupancy;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function branchIdOrFail(string $action, float $startedAt, array $context = []): int
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
}
