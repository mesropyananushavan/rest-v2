<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application;

use App\Modules\Payments\Domain\PaymentsDomainException;
use App\Modules\Payments\Infrastructure\Models\Cashbox;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;

final class DeactivateCashbox
{
    use RecordsCashboxAction;
    use RunsCashboxTransactions;

    public function __construct(
        private readonly TenantResolver $tenants,
        private readonly BranchContext $branches,
    ) {}

    public function __invoke(int $cashboxId, ?int $replacementDefaultId = null): Cashbox
    {
        $startedAt = microtime(true);
        $tenantId = $this->tenantIdOrFail('payments.cashboxes.deactivate', $startedAt, $cashboxId);
        $branchId = $this->branchIdOrFail('payments.cashboxes.deactivate', $startedAt, $cashboxId);

        $cashbox = $this->runCashboxTransaction(function () use ($branchId, $cashboxId, $replacementDefaultId, $startedAt, $tenantId): Cashbox {
            $this->lockCashboxBranch($tenantId, $branchId);

            $cashbox = Cashbox::query()
                ->where('branch_id', $branchId)
                ->lockForUpdate()
                ->findOrFail($cashboxId);

            if (! $cashbox->is_active) {
                return $cashbox;
            }

            $before = $this->cashboxAuditPayload($cashbox);
            $otherActiveCount = Cashbox::query()
                ->where('branch_id', $branchId)
                ->where('is_active', true)
                ->where('id', '<>', $cashboxId)
                ->lockForUpdate()
                ->count();

            if ($cashbox->is_default && $otherActiveCount > 0) {
                $this->selectReplacementOrFail($branchId, $cashboxId, $replacementDefaultId, $startedAt);
            }

            $cashbox->update([
                'is_active' => false,
                'is_default' => false,
            ]);

            $this->auditCashboxMutation(
                'payments.cashbox.deactivated',
                (int) $cashbox->id,
                $before,
                $this->cashboxAuditPayload($cashbox->refresh()),
            );

            return $cashbox;
        });

        $this->logSuccess('payments.cashboxes.deactivate', $startedAt, [
            'branch_id' => $branchId,
            'cashbox_id' => (int) $cashbox->id,
            'replacement_default_id' => $replacementDefaultId,
        ]);

        return $cashbox;
    }

    private function selectReplacementOrFail(int $branchId, int $cashboxId, ?int $replacementDefaultId, float $startedAt): void
    {
        if ($replacementDefaultId === null) {
            $exception = PaymentsDomainException::defaultReplacementRequired();
            $this->logDomainFailure('payments.cashboxes.deactivate', $exception, $startedAt, [
                'branch_id' => $branchId,
                'cashbox_id' => $cashboxId,
            ]);

            throw $exception;
        }

        if ($replacementDefaultId === $cashboxId) {
            $exception = PaymentsDomainException::replacementCashboxInvalid();
            $this->logDomainFailure('payments.cashboxes.deactivate', $exception, $startedAt, [
                'branch_id' => $branchId,
                'cashbox_id' => $cashboxId,
                'replacement_default_id' => $replacementDefaultId,
            ]);

            throw $exception;
        }

        $replacement = Cashbox::query()
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->lockForUpdate()
            ->find($replacementDefaultId);

        if (! $replacement instanceof Cashbox) {
            $exception = PaymentsDomainException::replacementCashboxInvalid();
            $this->logDomainFailure('payments.cashboxes.deactivate', $exception, $startedAt, [
                'branch_id' => $branchId,
                'cashbox_id' => $cashboxId,
                'replacement_default_id' => $replacementDefaultId,
            ]);

            throw $exception;
        }

        $replacementBefore = $this->cashboxAuditPayload($replacement);

        Cashbox::query()
            ->where('branch_id', $branchId)
            ->where('is_default', true)
            ->update(['is_default' => false]);

        $replacement->update(['is_default' => true]);

        $this->auditCashboxMutation(
            'payments.cashbox.default_selected',
            (int) $replacement->id,
            $replacementBefore,
            $this->cashboxAuditPayload($replacement->refresh()),
        );
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
