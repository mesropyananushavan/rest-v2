<?php

declare(strict_types=1);

namespace App\Modules\Orders\Application;

use App\Modules\Orders\Domain\OrdersDomainException;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Modules\Tenancy\Contracts\TenantSettingsReader;
use App\Support\Money\Money;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class OpenTablelessOrder
{
    use RecordsOrderAction;

    private const array ALLOWED_TYPES = [
        'fast_food',
        'takeaway',
        'delivery',
    ];

    public function __construct(
        private readonly TenantResolver $tenants,
        private readonly BranchContext $branches,
        private readonly TenantSettingsReader $settings,
    ) {}

    public function __invoke(
        string $type,
        ?int $waiterId = null,
        int $clientCount = 1,
        ?string $comment = null,
        ?int $customerId = null,
    ): Order {
        $startedAt = microtime(true);

        if (! in_array($type, self::ALLOWED_TYPES, true)) {
            $exception = OrdersDomainException::invalidOrderType();
            $this->logDomainFailure('orders.tableless.open', $exception, $startedAt, [
                'type' => $type,
            ]);

            throw $exception;
        }

        $tenantId = $this->tenantIdOrFail('orders.tableless.open', $startedAt, [
            'type' => $type,
        ]);
        $branchId = $this->branchIdOrFail('orders.tableless.open', $startedAt, [
            'type' => $type,
        ]);
        $waiterId ??= $this->actingUserId();
        $currency = $this->currency($tenantId);

        $order = DB::transaction(function () use ($branchId, $clientCount, $comment, $currency, $customerId, $type, $waiterId): Order {
            $zero = new Money(0, $currency);
            $openedAt = now();

            $order = Order::query()->create([
                'branch_id' => $branchId,
                'type' => $type,
                'status' => 'open',
                'table_id' => null,
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

        $this->logSuccess('orders.tableless.open', $startedAt, [
            'branch_id' => $branchId,
            'order_id' => (int) $order->id,
            'type' => $type,
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
}
