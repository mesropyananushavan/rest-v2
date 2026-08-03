<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application;

use App\Modules\Payments\Domain\PaymentsDomainException;
use App\Modules\Payments\Infrastructure\Models\Cashbox;
use App\Modules\Tenancy\Contracts\BranchContext;

final class FindCashbox
{
    use RecordsCashboxAction;

    public function __construct(
        private readonly BranchContext $branches,
    ) {}

    public function __invoke(int $cashboxId): Cashbox
    {
        $startedAt = microtime(true);
        $branchId = $this->branchIdOrFail('payments.cashboxes.find', $startedAt, $cashboxId);

        $cashbox = Cashbox::query()
            ->where('branch_id', $branchId)
            ->findOrFail($cashboxId);

        $this->logSuccess('payments.cashboxes.find', $startedAt, [
            'branch_id' => $branchId,
            'cashbox_id' => $cashboxId,
        ]);

        return $cashbox;
    }

    private function branchIdOrFail(string $action, float $startedAt, int $cashboxId): int
    {
        $branchId = $this->branches->id();

        if ($branchId !== null) {
            return $branchId;
        }

        $exception = PaymentsDomainException::branchContextRequired();
        $this->logDomainFailure($action, $exception, $startedAt, [
            'cashbox_id' => $cashboxId,
        ]);

        throw $exception;
    }
}
