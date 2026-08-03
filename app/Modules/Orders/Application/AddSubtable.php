<?php

declare(strict_types=1);

namespace App\Modules\Orders\Application;

use App\Modules\Orders\Domain\OrdersDomainException;
use App\Modules\Orders\Infrastructure\Models\OrderSubtable;
use App\Modules\Tenancy\Contracts\BranchContext;

final class AddSubtable
{
    use LocksOrdersForUpdate;
    use RecordsOrderAction;
    use RunsOrderTransactions;

    private const int NAME_MAX_LENGTH = 60;

    public function __construct(
        private readonly BranchContext $branches,
    ) {}

    public function __invoke(int $orderId, string $name): OrderSubtable
    {
        $startedAt = microtime(true);
        $branchId = $this->branchIdOrFail('orders.subtables.add', $startedAt, [
            'order_id' => $orderId,
        ]);

        $name = $this->validatedName($name, $branchId, $orderId, $startedAt);

        $subtable = $this->runOrderTransaction(function () use ($branchId, $name, $orderId, $startedAt): OrderSubtable {
            $order = $this->lockOpenOrderForUpdate($orderId, $branchId, 'orders.subtables.add', $startedAt, []);

            $this->ensureUniqueOpenSubtableName((int) $order->id, $branchId, $name, $startedAt);

            $subtable = OrderSubtable::query()->create([
                'branch_id' => $branchId,
                'order_id' => (int) $order->id,
                'name' => $name,
                'status' => 'open',
            ]);

            $this->auditOrderMutation(
                'orders.subtable.added',
                'orders_subtable',
                (int) $subtable->id,
                null,
                $this->orderSubtableAuditPayload($subtable),
            );

            return $subtable;
        });

        $this->logSuccess('orders.subtables.add', $startedAt, [
            'branch_id' => $branchId,
            'order_id' => $orderId,
            'subtable_id' => (int) $subtable->id,
        ]);

        return $subtable;
    }

    private function validatedName(string $name, int $branchId, int $orderId, float $startedAt): string
    {
        $name = trim($name);

        if ($name === '') {
            $exception = OrdersDomainException::subtableNameRequired();
            $this->logDomainFailure('orders.subtables.add', $exception, $startedAt, [
                'branch_id' => $branchId,
                'order_id' => $orderId,
            ]);

            throw $exception;
        }

        if (mb_strlen($name) > self::NAME_MAX_LENGTH) {
            $exception = OrdersDomainException::subtableNameTooLong();
            $this->logDomainFailure('orders.subtables.add', $exception, $startedAt, [
                'branch_id' => $branchId,
                'order_id' => $orderId,
                'max_length' => self::NAME_MAX_LENGTH,
            ]);

            throw $exception;
        }

        return $name;
    }

    private function ensureUniqueOpenSubtableName(int $orderId, int $branchId, string $name, float $startedAt): void
    {
        $normalizedName = mb_strtolower(trim($name));

        $exists = OrderSubtable::query()
            ->where('branch_id', $branchId)
            ->where('order_id', $orderId)
            ->where('status', 'open')
            ->get(['name'])
            ->contains(
                static fn (OrderSubtable $subtable): bool => mb_strtolower(trim((string) $subtable->name)) === $normalizedName,
            );

        if (! $exists) {
            return;
        }

        $exception = OrdersDomainException::subtableNameDuplicate();
        $this->logDomainFailure('orders.subtables.add', $exception, $startedAt, [
            'branch_id' => $branchId,
            'order_id' => $orderId,
        ]);

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
}
