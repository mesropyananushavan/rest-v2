<?php

declare(strict_types=1);

namespace App\Modules\Orders\Application;

use App\Modules\Orders\Domain\OrdersDomainException;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Tables\Contracts\TableDirectory;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Modules\Tenancy\Contracts\TenantSettingsReader;
use App\Support\Money\Money;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;

final class OpenOrder
{
    use RecordsOrderAction;
    use RunsOrderTransactions;

    public function __construct(
        private readonly TenantResolver $tenants,
        private readonly BranchContext $branches,
        private readonly TenantSettingsReader $settings,
        private readonly TableDirectory $tables,
    ) {}

    public function __invoke(
        int $tableId,
        ?int $waiterId = null,
        int $clientCount = 1,
        ?string $comment = null,
        ?int $customerId = null,
    ): Order {
        $startedAt = microtime(true);
        $tenantId = $this->tenantIdOrFail('orders.open', $startedAt, [
            'table_id' => $tableId,
        ]);
        $branchId = $this->branchIdOrFail('orders.open', $startedAt, [
            'table_id' => $tableId,
        ]);
        $waiterId ??= $this->actingUserId();
        $currency = $this->currency($tenantId);

        try {
            $order = $this->runOrderTransaction(function () use ($branchId, $clientCount, $comment, $currency, $customerId, $startedAt, $tableId, $waiterId): Order {
                $table = $this->tables->findActiveInBranch($tableId, $branchId);

                if ($table === null) {
                    $exception = OrdersDomainException::tableNotFound();
                    $this->logDomainFailure('orders.open', $exception, $startedAt, [
                        'branch_id' => $branchId,
                        'table_id' => $tableId,
                    ]);

                    throw $exception;
                }

                $alreadyOpen = Order::query()
                    ->where('branch_id', $branchId)
                    ->where('table_id', $tableId)
                    ->where('type', 'dine_in')
                    ->where('status', 'open')
                    ->lockForUpdate()
                    ->exists();

                if ($alreadyOpen) {
                    $exception = OrdersDomainException::tableAlreadyOpen();
                    $this->logDomainFailure('orders.open', $exception, $startedAt, [
                        'branch_id' => $branchId,
                        'table_id' => $tableId,
                    ]);

                    throw $exception;
                }

                $zero = new Money(0, $currency);
                $openedAt = now();

                $order = Order::query()->create([
                    'branch_id' => $branchId,
                    'type' => 'dine_in',
                    'status' => 'open',
                    'table_id' => $table->id,
                    'customer_id' => $customerId,
                    'waiter_id' => $waiterId,
                    'cashier_id' => null,
                    'opened_at' => $openedAt,
                    'closed_at' => null,
                    'client_count' => max(1, $clientCount),
                    'comment' => $comment,
                    'subtotal_minor' => $zero->minor,
                    'discount_minor' => $zero->minor,
                    'total_minor' => $zero->minor,
                    'currency' => $zero->currency,
                ]);

                $this->auditOrderMutation(
                    'orders.order.opened',
                    'orders_order',
                    (int) $order->id,
                    null,
                    $this->orderAuditPayload($order),
                );

                return $order;
            });
        } catch (QueryException $exception) {
            if (! $this->isOpenOrderUniqueViolation($exception)) {
                throw $exception;
            }

            $domainException = OrdersDomainException::tableAlreadyOpen();
            $this->logDomainFailure('orders.open', $domainException, $startedAt, [
                'branch_id' => $branchId,
                'table_id' => $tableId,
            ]);

            throw $domainException;
        }

        $this->logSuccess('orders.open', $startedAt, [
            'branch_id' => $branchId,
            'order_id' => (int) $order->id,
            'table_id' => $tableId,
            'waiter_id' => $waiterId,
        ]);

        return $order;
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

    private function currency(int $tenantId): string
    {
        return $this->settings->settingsFor($tenantId)['currency'] ?? 'AMD';
    }

    private function actingUserId(): ?int
    {
        $userId = Auth::id();

        return is_numeric($userId) ? (int) $userId : null;
    }

    private function isOpenOrderUniqueViolation(QueryException $exception): bool
    {
        return (string) $exception->getCode() === '23505'
            && str_contains($exception->getMessage(), 'orders_one_open_dine_in_per_table_idx');
    }
}
