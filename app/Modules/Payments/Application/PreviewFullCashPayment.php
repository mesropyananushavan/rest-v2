<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application;

use App\Modules\Orders\Contracts\PayableOrderReader;
use App\Modules\Payments\Domain\PaymentsDomainException;
use App\Support\Logging\LogContext;
use App\Support\Logging\Redactor;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class PreviewFullCashPayment
{
    public function __construct(
        private readonly PayableOrderReader $payableOrders,
        private readonly CalculateOrderPaymentBalance $balances,
    ) {}

    public function __invoke(int $orderId): FullCashPaymentPreview
    {
        $startedAt = microtime(true);

        try {
            $order = $this->payableOrders->findPayable($orderId);
            $remainingMinor = $this->balances->remainingMinor($order, $order->tenantId, $order->branchId);
        } catch (PaymentsDomainException $exception) {
            $this->logDomainFailure('payments.full_cash_payment.preview', $exception->errorCode(), $startedAt, [
                'order_id' => $orderId,
            ]);

            throw $exception;
        } catch (RuntimeException $exception) {
            if (! method_exists($exception, 'errorCode')) {
                throw $exception;
            }

            /** @var callable(): string $errorCode */
            $errorCode = [$exception, 'errorCode'];
            $this->logDomainFailure('payments.full_cash_payment.preview', $errorCode(), $startedAt, [
                'order_id' => $orderId,
            ]);

            throw $exception;
        }

        $this->logSuccess('payments.full_cash_payment.preview', $startedAt, [
            'tenant_id' => $order->tenantId,
            'branch_id' => $order->branchId,
            'order_id' => $order->orderId,
            'amount_minor' => $remainingMinor,
            'currency' => $order->currency,
        ]);

        return new FullCashPaymentPreview(
            orderId: $order->orderId,
            amountMinor: $remainingMinor,
            currency: $order->currency,
        );
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function logSuccess(string $action, float $startedAt, array $context): void
    {
        LogContext::refreshRuntimeContext('payments');

        Log::info('action performed', Redactor::context([
            'action' => $action,
            'duration_ms' => $this->durationMs($startedAt),
        ] + $context));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function logDomainFailure(string $action, string $errorCode, float $startedAt, array $context = []): void
    {
        LogContext::refreshRuntimeContext('payments');

        Log::warning('action failed', Redactor::context([
            'action' => $action,
            'error_code' => $errorCode,
            'duration_ms' => $this->durationMs($startedAt),
        ] + $context));
    }

    private function durationMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
