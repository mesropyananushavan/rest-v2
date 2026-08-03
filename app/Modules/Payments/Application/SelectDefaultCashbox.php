<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application;

use App\Modules\Payments\Domain\PaymentsDomainException;
use App\Modules\Payments\Infrastructure\Models\Cashbox;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;

final class SelectDefaultCashbox
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
        $tenantId = $this->tenantIdOrFail('payments.cashboxes.select_default', $startedAt, $cashboxId);
        $branchId = $this->branchIdOrFail('payments.cashboxes.select_default', $startedAt, $cashboxId);

        $cashbox = $this->runCashboxTransaction(function () use ($branchId, $cashboxId, $startedAt, $tenantId): Cashbox {
            $this->lockCashboxBranch($tenantId, $branchId);

            $cashbox = Cashbox::query()
                ->where('branch_id', $branchId)
                ->lockForUpdate()
                ->findOrFail($cashboxId);

            if (! $cashbox->is_active) {
                $exception = PaymentsDomainException::defaultCashboxMustBeActive();
                $this->logDomainFailure('payments.cashboxes.select_default', $exception, $startedAt, [
                    'branch_id' => $branchId,
                    'cashbox_id' => $cashboxId,
                ]);

                throw $exception;
            }

            $before = $this->cashboxAuditPayload($cashbox);

            Cashbox::query()
                ->where('branch_id', $branchId)
                ->where('is_default', true)
                ->update(['is_default' => false]);

            $cashbox->update(['is_default' => true]);

            $this->auditCashboxMutation(
                'payments.cashbox.default_selected',
                (int) $cashbox->id,
                $before,
                $this->cashboxAuditPayload($cashbox->refresh()),
            );

            return $cashbox;
        });

        $this->logSuccess('payments.cashboxes.select_default', $startedAt, [
            'branch_id' => $branchId,
            'cashbox_id' => (int) $cashbox->id,
        ]);

        return $cashbox;
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
