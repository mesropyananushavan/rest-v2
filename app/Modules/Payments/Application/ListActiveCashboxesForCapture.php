<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application;

use App\Modules\Payments\Domain\PaymentsDomainException;
use App\Modules\Payments\Infrastructure\Models\Cashbox;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;

final class ListActiveCashboxesForCapture
{
    use RecordsCashboxAction;

    public function __construct(
        private readonly TenantResolver $tenants,
        private readonly BranchContext $branches,
    ) {}

    /**
     * @return list<CashboxCaptureOption>
     */
    public function __invoke(): array
    {
        $startedAt = microtime(true);
        $tenantId = $this->tenantIdOrFail('payments.cashboxes.capture_options', $startedAt);
        $branchId = $this->branchIdOrFail('payments.cashboxes.capture_options', $startedAt);

        $cashboxes = Cashbox::query()
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name', 'is_default']);

        $this->logSuccess('payments.cashboxes.capture_options', $startedAt, [
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
            'cashbox_count' => $cashboxes->count(),
        ]);

        return array_values($cashboxes
            ->map(fn (Cashbox $cashbox): CashboxCaptureOption => new CashboxCaptureOption(
                id: (int) $cashbox->id,
                name: (string) $cashbox->name,
                isDefault: (bool) $cashbox->is_default,
            ))
            ->all());
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
