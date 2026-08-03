<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application;

use App\Modules\Payments\Domain\PaymentsDomainException;
use App\Modules\Payments\Infrastructure\Models\Cashbox;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;
use Illuminate\Database\QueryException;

final class ActivateCashbox
{
    use RecordsCashboxAction;
    use RunsCashboxTransactions;

    public function __construct(
        private readonly TenantResolver $tenants,
        private readonly BranchContext $branches,
    ) {}

    public function __invoke(int $cashboxId): Cashbox
    {
        $startedAt = microtime(true);
        $tenantId = $this->tenantIdOrFail('payments.cashboxes.activate', $startedAt, $cashboxId);
        $branchId = $this->branchIdOrFail('payments.cashboxes.activate', $startedAt, $cashboxId);

        try {
            $cashbox = $this->runCashboxTransaction(function () use ($branchId, $cashboxId, $startedAt, $tenantId): Cashbox {
                $this->lockCashboxBranch($tenantId, $branchId);

                $cashbox = Cashbox::query()
                    ->where('branch_id', $branchId)
                    ->lockForUpdate()
                    ->findOrFail($cashboxId);

                if ($cashbox->is_active) {
                    return $cashbox;
                }

                $this->ensureActiveNameIsAvailable($branchId, (string) $cashbox->name, $cashboxId, 'payments.cashboxes.activate', $startedAt);
                $before = $this->cashboxAuditPayload($cashbox);
                $isDefault = ! Cashbox::query()
                    ->where('branch_id', $branchId)
                    ->where('is_active', true)
                    ->where('is_default', true)
                    ->exists();

                $cashbox->update([
                    'is_active' => true,
                    'is_default' => $isDefault,
                ]);

                $this->auditCashboxMutation(
                    'payments.cashbox.activated',
                    (int) $cashbox->id,
                    $before,
                    $this->cashboxAuditPayload($cashbox->refresh()),
                );

                return $cashbox;
            });
        } catch (QueryException $exception) {
            $this->normalizeUniqueViolation($exception, 'payments.cashboxes.activate', $startedAt, $branchId, $cashboxId);
        }

        $this->logSuccess('payments.cashboxes.activate', $startedAt, [
            'branch_id' => $branchId,
            'cashbox_id' => (int) $cashbox->id,
        ]);

        return $cashbox;
    }

    private function normalizeUniqueViolation(QueryException $exception, string $action, float $startedAt, int $branchId, int $cashboxId): never
    {
        if ($this->isCashboxActiveNameUniqueViolation($exception)) {
            $domainException = PaymentsDomainException::cashboxNameDuplicate();
            $this->logDomainFailure($action, $domainException, $startedAt, [
                'branch_id' => $branchId,
                'cashbox_id' => $cashboxId,
            ]);

            throw $domainException;
        }

        throw $exception;
    }

    private function tenantIdOrFail(string $action, float $startedAt, int $cashboxId): int
    {
        $tenantId = $this->tenants->id();

        if ($tenantId !== null) {
            return $tenantId;
        }

        $exception = PaymentsDomainException::tenantContextRequired();
        $this->logDomainFailure($action, $exception, $startedAt, ['cashbox_id' => $cashboxId]);

        throw $exception;
    }

    private function branchIdOrFail(string $action, float $startedAt, int $cashboxId): int
    {
        $branchId = $this->branches->id();

        if ($branchId !== null) {
            return $branchId;
        }

        $exception = PaymentsDomainException::branchContextRequired();
        $this->logDomainFailure($action, $exception, $startedAt, ['cashbox_id' => $cashboxId]);

        throw $exception;
    }
}
