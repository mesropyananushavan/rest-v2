<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application;

use App\Modules\Identity\Contracts\Authorizer;
use App\Modules\Orders\Contracts\PayableOrderReader;
use App\Modules\Orders\Contracts\PayableOrderSnapshot;
use App\Modules\Payments\Contracts\PaymentPermissions;
use App\Modules\Payments\Domain\PaymentsDomainException;
use App\Modules\Payments\Infrastructure\Models\Cashbox;
use App\Modules\Payments\Infrastructure\Models\CashboxEntry;
use App\Modules\Payments\Infrastructure\Models\Payment;
use App\Modules\Payments\Infrastructure\Models\PaymentAllocation;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Support\Audit\AuditRecorder;
use App\Support\Logging\LogContext;
use App\Support\Logging\Redactor;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class CaptureCashPayment
{
    public function __construct(
        private readonly TenantResolver $tenants,
        private readonly BranchContext $branches,
        private readonly Authorizer $authorizer,
        private readonly PayableOrderReader $payableOrders,
        private readonly CalculateOrderPaymentBalance $balances,
        private readonly CaptureCashPaymentFingerprint $fingerprints,
        private readonly AuditRecorder $audits,
    ) {}

    public function __invoke(CaptureCashPaymentCommand $command): CaptureCashPaymentResult
    {
        $startedAt = microtime(true);
        $tenantId = $this->tenantIdOrFail('payments.capture_cash_payment', $startedAt, $command);
        $branchId = $this->branchIdOrFail('payments.capture_cash_payment', $startedAt, $command);
        $actor = $this->actorOrFail($tenantId, 'payments.capture_cash_payment', $startedAt, $command);
        $actorId = $this->actorIdOrFail($actor, $tenantId, 'payments.capture_cash_payment', $startedAt, $command);

        $this->authorize($actor, 'payments.capture_cash_payment', $startedAt, $command, $tenantId, $branchId);
        $this->validateCommand($command, 'payments.capture_cash_payment', $startedAt, $tenantId, $branchId);

        $fingerprint = $this->fingerprints->forCommand($command);

        $existing = $this->findCommittedPayment($tenantId, $branchId, $command->idempotencyKey);
        if ($existing instanceof Payment) {
            $result = $this->resultFromCommittedPayment($existing, $fingerprint, true, $startedAt);
            $this->logCaptureSuccess($result, $startedAt);

            return $result;
        }

        try {
            $result = DB::transaction(function () use ($actorId, $branchId, $command, $fingerprint, $tenantId): CaptureCashPaymentResult {
                $order = $this->payableOrders->lockPayableForUpdate($command->orderId);

                $existing = $this->findCommittedPayment($tenantId, $branchId, $command->idempotencyKey);
                if ($existing instanceof Payment) {
                    return $this->resultFromCommittedPayment($existing, $fingerprint, true);
                }

                $cashbox = Cashbox::query()
                    ->where('branch_id', $branchId)
                    ->lockForUpdate()
                    ->findOrFail($command->cashboxId);

                if (! $cashbox->is_active) {
                    throw PaymentsDomainException::cashboxUnavailable();
                }

                $remainingMinor = $this->balances->remainingMinor($order, $tenantId, $branchId);

                if ($order->currency !== $command->expectedCurrency) {
                    throw PaymentsDomainException::expectedCurrencyMismatch();
                }

                if ($remainingMinor !== $command->expectedAmountMinor) {
                    throw PaymentsDomainException::expectedAmountMismatch();
                }

                $payment = Payment::query()->create([
                    'branch_id' => $branchId,
                    'order_id' => $order->orderId,
                    'cashbox_id' => (int) $cashbox->id,
                    'method' => 'cash',
                    'status' => 'captured',
                    'amount_minor' => $remainingMinor,
                    'currency' => $order->currency,
                    'idempotency_key' => $command->idempotencyKey,
                    'idempotency_fingerprint' => $fingerprint,
                ]);

                $allocation = PaymentAllocation::query()->create([
                    'branch_id' => $branchId,
                    'payment_id' => (int) $payment->id,
                    'payable_type' => 'order',
                    'payable_id' => $order->orderId,
                    'amount_minor' => $remainingMinor,
                    'currency' => $order->currency,
                ]);

                $cashboxEntry = CashboxEntry::query()->create([
                    'branch_id' => $branchId,
                    'cashbox_id' => (int) $cashbox->id,
                    'direction' => 'in',
                    'amount_minor' => $remainingMinor,
                    'currency' => $order->currency,
                    'reason' => 'cash_payment',
                    'source_type' => 'payment',
                    'source_id' => (int) $payment->id,
                    'posted_by_id' => $actorId,
                ]);

                $this->auditCapture($payment, $allocation, $cashboxEntry, $order, $command);

                return new CaptureCashPaymentResult(
                    paymentId: (int) $payment->id,
                    paymentAllocationId: (int) $allocation->id,
                    cashboxEntryId: (int) $cashboxEntry->id,
                    tenantId: $tenantId,
                    branchId: $branchId,
                    orderId: $order->orderId,
                    cashboxId: (int) $cashbox->id,
                    amountMinor: $remainingMinor,
                    currency: $order->currency,
                    idempotencyKey: $command->idempotencyKey,
                    idempotencyFingerprint: $fingerprint,
                    replayed: false,
                );
            });
        } catch (PaymentsDomainException $exception) {
            $this->logDomainFailure('payments.capture_cash_payment', $exception, $startedAt, [
                'tenant_id' => $tenantId,
                'branch_id' => $branchId,
                'order_id' => $command->orderId,
                'cashbox_id' => $command->cashboxId,
            ]);

            throw $exception;
        } catch (QueryException $exception) {
            if (! $this->isIdempotencyUniqueViolation($exception)) {
                throw $exception;
            }

            $existing = $this->findCommittedPayment($tenantId, $branchId, $command->idempotencyKey);

            if (! $existing instanceof Payment) {
                throw $exception;
            }

            $result = $this->resultFromCommittedPayment($existing, $fingerprint, true, $startedAt);
            $this->logCaptureSuccess($result, $startedAt);

            return $result;
        }

        $this->logCaptureSuccess($result, $startedAt);

        return $result;
    }

    private function logCaptureSuccess(CaptureCashPaymentResult $result, float $startedAt): void
    {
        $this->logSuccess('payments.capture_cash_payment', $startedAt, [
            'tenant_id' => $result->tenantId,
            'branch_id' => $result->branchId,
            'order_id' => $result->orderId,
            'cashbox_id' => $result->cashboxId,
            'payment_id' => $result->paymentId,
            'amount_minor' => $result->amountMinor,
            'currency' => $result->currency,
            'replayed' => $result->replayed,
        ]);
    }

    private function findCommittedPayment(int $tenantId, int $branchId, string $idempotencyKey): ?Payment
    {
        return Payment::query()
            ->where('tenant_id', $tenantId)
            ->where('branch_id', $branchId)
            ->where('idempotency_key', $idempotencyKey)
            ->first();
    }

    private function resultFromCommittedPayment(
        Payment $payment,
        string $expectedFingerprint,
        bool $replayed,
        ?float $startedAt = null,
    ): CaptureCashPaymentResult {
        if ($payment->idempotency_fingerprint !== $expectedFingerprint) {
            $exception = PaymentsDomainException::idempotencyConflict();

            if ($startedAt !== null) {
                $this->logDomainFailure('payments.capture_cash_payment', $exception, $startedAt, [
                    'tenant_id' => (int) $payment->tenant_id,
                    'branch_id' => (int) $payment->branch_id,
                    'order_id' => (int) $payment->order_id,
                    'cashbox_id' => (int) $payment->cashbox_id,
                    'payment_id' => (int) $payment->id,
                ]);
            }

            throw $exception;
        }

        $allocation = PaymentAllocation::query()
            ->where('payment_id', (int) $payment->id)
            ->where('payable_type', 'order')
            ->where('payable_id', (int) $payment->order_id)
            ->firstOrFail();
        $entry = CashboxEntry::query()
            ->where('source_type', 'payment')
            ->where('source_id', (int) $payment->id)
            ->firstOrFail();

        return new CaptureCashPaymentResult(
            paymentId: (int) $payment->id,
            paymentAllocationId: (int) $allocation->id,
            cashboxEntryId: (int) $entry->id,
            tenantId: (int) $payment->tenant_id,
            branchId: (int) $payment->branch_id,
            orderId: (int) $payment->order_id,
            cashboxId: (int) $payment->cashbox_id,
            amountMinor: (int) $payment->amount_minor,
            currency: (string) $payment->currency,
            idempotencyKey: (string) $payment->idempotency_key,
            idempotencyFingerprint: (string) $payment->idempotency_fingerprint,
            replayed: $replayed,
        );
    }

    private function auditCapture(
        Payment $payment,
        PaymentAllocation $allocation,
        CashboxEntry $cashboxEntry,
        PayableOrderSnapshot $order,
        CaptureCashPaymentCommand $command,
    ): void {
        LogContext::refreshRuntimeContext('payments');

        $this->audits->record('payments.payment.captured', 'payments_payment', (int) $payment->id, null, [
            'payment_id' => (int) $payment->id,
            'payment_allocation_id' => (int) $allocation->id,
            'cashbox_entry_id' => (int) $cashboxEntry->id,
            'branch_id' => (int) $payment->branch_id,
            'order_id' => $order->orderId,
            'cashbox_id' => (int) $payment->cashbox_id,
            'method' => 'cash',
            'status' => 'captured',
            'amount_minor' => (int) $payment->amount_minor,
            'currency' => (string) $payment->currency,
            'idempotency_key' => $command->idempotencyKey,
            'idempotency_fingerprint' => (string) $payment->idempotency_fingerprint,
        ]);
    }

    private function validateCommand(CaptureCashPaymentCommand $command, string $action, float $startedAt, int $tenantId, int $branchId): void
    {
        if ($command->expectedAmountMinor <= 0) {
            $this->throwDomainFailure(PaymentsDomainException::captureAmountMustBePositive(), $action, $startedAt, $tenantId, $branchId, $command);
        }

        if (! preg_match('/^[A-Z]{3}$/', $command->expectedCurrency)) {
            $this->throwDomainFailure(PaymentsDomainException::captureCurrencyInvalid(), $action, $startedAt, $tenantId, $branchId, $command);
        }

        if ($command->idempotencyKey === '') {
            $this->throwDomainFailure(PaymentsDomainException::idempotencyKeyRequired(), $action, $startedAt, $tenantId, $branchId, $command);
        }

        if (mb_strlen($command->idempotencyKey) > 128) {
            $this->throwDomainFailure(PaymentsDomainException::idempotencyKeyTooLong(), $action, $startedAt, $tenantId, $branchId, $command);
        }

        if (preg_match('/[[:cntrl:]]/', $command->idempotencyKey)) {
            $this->throwDomainFailure(PaymentsDomainException::idempotencyKeyControlCharacters(), $action, $startedAt, $tenantId, $branchId, $command);
        }

        if ($command->idempotencyKey !== trim($command->idempotencyKey)) {
            $this->throwDomainFailure(PaymentsDomainException::idempotencyKeyWhitespace(), $action, $startedAt, $tenantId, $branchId, $command);
        }
    }

    private function throwDomainFailure(
        PaymentsDomainException $exception,
        string $action,
        float $startedAt,
        int $tenantId,
        int $branchId,
        CaptureCashPaymentCommand $command,
    ): never {
        $this->logDomainFailure($action, $exception, $startedAt, [
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
            'order_id' => $command->orderId,
            'cashbox_id' => $command->cashboxId,
        ]);

        throw $exception;
    }

    private function tenantIdOrFail(string $action, float $startedAt, CaptureCashPaymentCommand $command): int
    {
        $tenantId = $this->tenants->id();

        if ($tenantId !== null) {
            return $tenantId;
        }

        $exception = PaymentsDomainException::tenantContextRequired();
        $this->logDomainFailure($action, $exception, $startedAt, [
            'order_id' => $command->orderId,
            'cashbox_id' => $command->cashboxId,
        ]);

        throw $exception;
    }

    private function branchIdOrFail(string $action, float $startedAt, CaptureCashPaymentCommand $command): int
    {
        $branchId = $this->branches->id();

        if ($branchId !== null) {
            return $branchId;
        }

        $exception = PaymentsDomainException::branchContextRequired();
        $this->logDomainFailure($action, $exception, $startedAt, [
            'order_id' => $command->orderId,
            'cashbox_id' => $command->cashboxId,
        ]);

        throw $exception;
    }

    private function actorOrFail(
        int $tenantId,
        string $action,
        float $startedAt,
        CaptureCashPaymentCommand $command,
    ): Authenticatable {
        $actor = Auth::user();
        $actorTenantId = data_get($actor, 'tenant_id');

        if ($actor instanceof Authenticatable && is_numeric($actorTenantId) && (int) $actorTenantId === $tenantId) {
            return $actor;
        }

        $exception = PaymentsDomainException::actorContextRequired();
        $this->logDomainFailure($action, $exception, $startedAt, [
            'tenant_id' => $tenantId,
            'order_id' => $command->orderId,
            'cashbox_id' => $command->cashboxId,
        ]);

        throw $exception;
    }

    private function actorIdOrFail(
        Authenticatable $actor,
        int $tenantId,
        string $action,
        float $startedAt,
        CaptureCashPaymentCommand $command,
    ): int {
        $actorId = $actor->getAuthIdentifier();

        if (is_int($actorId)) {
            return $actorId;
        }

        if (is_string($actorId) && ctype_digit($actorId)) {
            return (int) $actorId;
        }

        $exception = PaymentsDomainException::actorContextRequired();
        $this->logDomainFailure($action, $exception, $startedAt, [
            'tenant_id' => $tenantId,
            'order_id' => $command->orderId,
            'cashbox_id' => $command->cashboxId,
        ]);

        throw $exception;
    }

    private function authorize(
        Authenticatable $actor,
        string $action,
        float $startedAt,
        CaptureCashPaymentCommand $command,
        int $tenantId,
        int $branchId,
    ): void {
        if ($this->authorizer->allows($actor, PaymentPermissions::CAPTURE)) {
            return;
        }

        LogContext::refreshRuntimeContext('payments');
        Log::warning('permission denied', Redactor::context([
            'action' => $action,
            'permission' => PaymentPermissions::CAPTURE,
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
            'order_id' => $command->orderId,
            'cashbox_id' => $command->cashboxId,
            'duration_ms' => $this->durationMs($startedAt),
        ]));

        throw new AuthorizationException;
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
    private function logDomainFailure(string $action, PaymentsDomainException $exception, float $startedAt, array $context = []): void
    {
        LogContext::refreshRuntimeContext('payments');

        Log::warning('action failed', Redactor::context([
            'action' => $action,
            'error_code' => $exception->errorCode(),
            'duration_ms' => $this->durationMs($startedAt),
        ] + $context));
    }

    private function isIdempotencyUniqueViolation(QueryException $exception): bool
    {
        return in_array((string) $exception->getCode(), ['23000', '23505'], true)
            && str_contains($exception->getMessage(), 'payments_tenant_branch_idempotency_key_unique');
    }

    private function durationMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
