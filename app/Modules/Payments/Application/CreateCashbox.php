<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application;

use App\Modules\Payments\Domain\PaymentsDomainException;
use App\Modules\Payments\Infrastructure\Models\Cashbox;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;
use Illuminate\Database\QueryException;

final class CreateCashbox
{
    use RecordsCashboxAction;
    use RunsCashboxTransactions;

    public function __construct(
        private readonly TenantResolver $tenants,
        private readonly BranchContext $branches,
    ) {}

    public function __invoke(string $name, bool $isActive = true): Cashbox
    {
        $startedAt = microtime(true);
        $tenantId = $this->tenantIdOrFail('payments.cashboxes.create', $startedAt);
        $branchId = $this->branchIdOrFail('payments.cashboxes.create', $startedAt);
        $name = $this->validatedCashboxName($name, 'payments.cashboxes.create', $startedAt, $branchId);

        try {
            $cashbox = $this->runCashboxTransaction(function () use ($branchId, $isActive, $name, $startedAt, $tenantId): Cashbox {
                $this->lockCashboxBranch($tenantId, $branchId);

                if ($isActive) {
                    $this->ensureActiveNameIsAvailable($branchId, $name, null, 'payments.cashboxes.create', $startedAt);
                }

                $isDefault = $isActive && ! Cashbox::query()
                    ->where('branch_id', $branchId)
                    ->where('is_active', true)
                    ->where('is_default', true)
                    ->exists();

                $cashbox = Cashbox::query()->create([
                    'branch_id' => $branchId,
                    'name' => $name,
                    'is_active' => $isActive,
                    'is_default' => $isDefault,
                ]);

                $this->auditCashboxMutation(
                    'payments.cashbox.created',
                    (int) $cashbox->id,
                    null,
                    $this->cashboxAuditPayload($cashbox),
                );

                return $cashbox;
            });
        } catch (QueryException $exception) {
            $this->normalizeUniqueViolation($exception, 'payments.cashboxes.create', $startedAt, $branchId);
        }

        $this->logSuccess('payments.cashboxes.create', $startedAt, [
            'branch_id' => $branchId,
            'cashbox_id' => (int) $cashbox->id,
        ]);

        return $cashbox;
    }

    private function normalizeUniqueViolation(QueryException $exception, string $action, float $startedAt, int $branchId): never
    {
        if ($this->isCashboxActiveNameUniqueViolation($exception)) {
            $domainException = PaymentsDomainException::cashboxNameDuplicate();
            $this->logDomainFailure($action, $domainException, $startedAt, ['branch_id' => $branchId]);

            throw $domainException;
        }

        throw $exception;
    }

    private function tenantIdOrFail(string $action, float $startedAt): int
    {
        $tenantId = $this->tenants->id();

        if ($tenantId !== null) {
            return $tenantId;
        }

        $exception = PaymentsDomainException::tenantContextRequired();
        $this->logDomainFailure($action, $exception, $startedAt);

        throw $exception;
    }

    private function branchIdOrFail(string $action, float $startedAt): int
    {
        $branchId = $this->branches->id();

        if ($branchId !== null) {
            return $branchId;
        }

        $exception = PaymentsDomainException::branchContextRequired();
        $this->logDomainFailure($action, $exception, $startedAt);

        throw $exception;
    }
}
