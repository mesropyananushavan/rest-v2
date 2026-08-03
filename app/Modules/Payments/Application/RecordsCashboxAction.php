<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application;

use App\Modules\Payments\Domain\PaymentsDomainException;
use App\Modules\Payments\Infrastructure\Models\Cashbox;
use App\Support\Audit\AuditRecorder;
use App\Support\Logging\LogContext;
use App\Support\Logging\Redactor;
use DateTimeInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

trait RecordsCashboxAction
{
    private const int CASHBOX_NAME_MAX_LENGTH = 255;

    /**
     * @param  array<string, mixed>  $context
     */
    private function logSuccess(string $action, float $startedAt, array $context = []): void
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

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    private function auditCashboxMutation(string $action, int $targetId, ?array $before, ?array $after): void
    {
        LogContext::refreshRuntimeContext('payments');

        app(AuditRecorder::class)->record($action, 'payments_cashbox', $targetId, $before, $after);
    }

    /**
     * @return array<string, mixed>
     */
    private function cashboxAuditPayload(Cashbox $cashbox): array
    {
        return [
            'id' => (int) $cashbox->id,
            'branch_id' => (int) $cashbox->branch_id,
            'name' => (string) $cashbox->name,
            'is_active' => (bool) $cashbox->is_active,
            'is_default' => (bool) $cashbox->is_default,
            'created_at' => $this->dateAuditValue($cashbox->created_at),
            'updated_at' => $this->dateAuditValue($cashbox->updated_at),
        ];
    }

    private function validatedCashboxName(string $name, string $action, float $startedAt, int $branchId, ?int $cashboxId = null): string
    {
        $name = trim($name);

        if ($name === '') {
            $exception = PaymentsDomainException::cashboxNameRequired();
            $this->logDomainFailure($action, $exception, $startedAt, [
                'branch_id' => $branchId,
                'cashbox_id' => $cashboxId,
            ]);

            throw $exception;
        }

        if (mb_strlen($name) > self::CASHBOX_NAME_MAX_LENGTH) {
            $exception = PaymentsDomainException::cashboxNameTooLong();
            $this->logDomainFailure($action, $exception, $startedAt, [
                'branch_id' => $branchId,
                'cashbox_id' => $cashboxId,
                'max_length' => self::CASHBOX_NAME_MAX_LENGTH,
            ]);

            throw $exception;
        }

        return $name;
    }

    private function lockCashboxBranch(int $tenantId, int $branchId): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('select pg_advisory_xact_lock(hashtextextended(?, 0))', ["payments.cashboxes.{$tenantId}.{$branchId}"]);
        }
    }

    private function ensureActiveNameIsAvailable(int $branchId, string $name, ?int $exceptCashboxId, string $action, float $startedAt): void
    {
        $exists = Cashbox::query()
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->whereRaw('lower(name) = ?', [mb_strtolower($name)])
            ->when($exceptCashboxId !== null, fn ($query) => $query->where('id', '<>', $exceptCashboxId))
            ->exists();

        if (! $exists) {
            return;
        }

        $exception = PaymentsDomainException::cashboxNameDuplicate();
        $this->logDomainFailure($action, $exception, $startedAt, [
            'branch_id' => $branchId,
            'cashbox_id' => $exceptCashboxId,
        ]);

        throw $exception;
    }

    private function isCashboxActiveNameUniqueViolation(QueryException $exception): bool
    {
        return (string) $exception->getCode() === '23505'
            && str_contains($exception->getMessage(), 'cashboxes_active_name_unique_idx');
    }

    private function isCashboxActiveDefaultUniqueViolation(QueryException $exception): bool
    {
        return (string) $exception->getCode() === '23505'
            && str_contains($exception->getMessage(), 'cashboxes_active_default_unique_idx');
    }

    private function durationMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }

    private function dateAuditValue(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }

        return is_string($value) && $value !== '' ? $value : null;
    }
}
