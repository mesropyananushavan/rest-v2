<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application;

use App\Modules\Payments\Domain\PaymentsDomainException;
use App\Modules\Payments\Infrastructure\Models\Cashbox;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;
use Illuminate\Database\QueryException;

final class UpdateCashbox
{
    use RecordsCashboxAction;
    use RunsCashboxTransactions;

    public function __construct(
        private readonly TenantResolver $tenants,
        private readonly BranchContext $branches,
    ) {}

    public function __invoke(int $cashboxId, string $name): Cashbox
    {
        $startedAt = microtime(true);
        $tenantId = $this->tenantIdOrFail('payments.cashboxes.update', $startedAt, $cashboxId);
        $branchId = $this->branchIdOrFail('payments.cashboxes.update', $startedAt, $cashboxId);
        $name = $this->validatedCashboxName($name, 'payments.cashboxes.update', $startedAt, $branchId, $cashboxId);

        try {
            $cashbox = $this->runCashboxTransaction(function () use ($branchId, $cashboxId, $name, $startedAt, $tenantId): Cashbox {
                $this->lockCashboxBranch($tenantId, $branchId);

                $cashbox = Cashbox::query()
                    ->where('branch_id', $branchId)
                    ->lockForUpdate()
                    ->findOrFail($cashboxId);
                $before = $this->cashboxAuditPayload($cashbox);

                if ($cashbox->is_active) {
                    $this->ensureActiveNameIsAvailable($branchId, $name, $cashboxId, 'payments.cashboxes.update', $startedAt);
                }

                $cashbox->update(['name' => $name]);

                $this->auditCashboxMutation(
                    'payments.cashbox.updated',
                    (int) $cashbox->id,
                    $before,
                    $this->cashboxAuditPayload($cashbox->refresh()),
                );

                return $cashbox;
            });
        } catch (QueryException $exception) {
            $this->normalizeUniqueViolation($exception, 'payments.cashboxes.update', $startedAt, $branchId, $cashboxId);
        }

        $this->logSuccess('payments.cashboxes.update', $startedAt, [
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
