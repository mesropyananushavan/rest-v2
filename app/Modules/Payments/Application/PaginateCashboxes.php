<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application;

use App\Modules\Payments\Domain\PaymentsDomainException;
use App\Modules\Payments\Infrastructure\Models\Cashbox;
use App\Modules\Tenancy\Contracts\BranchContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final class PaginateCashboxes
{
    use RecordsCashboxAction;

    private const int DEFAULT_PER_PAGE = 25;

    private const int MAX_PER_PAGE = 50;

    public function __construct(
        private readonly BranchContext $branches,
    ) {}

    /**
     * @return LengthAwarePaginator<int, Cashbox>
     */
    public function __invoke(bool $includeInactive = true, int $perPage = self::DEFAULT_PER_PAGE, int $page = 1): LengthAwarePaginator
    {
        $startedAt = microtime(true);
        $branchId = $this->branchIdOrFail('payments.cashboxes.paginate', $startedAt);
        $perPage = min(self::MAX_PER_PAGE, max(1, $perPage));
        $page = max(1, $page);

        /** @var LengthAwarePaginator<int, Cashbox> $cashboxes */
        $cashboxes = Cashbox::query()
            ->where('branch_id', $branchId)
            ->when(! $includeInactive, fn (Builder $query): Builder => $query->where('is_active', true))
            ->orderByDesc('is_active')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($perPage, ['*'], 'page', $page);

        $this->logSuccess('payments.cashboxes.paginate', $startedAt, [
            'branch_id' => $branchId,
            'cashbox_count' => $cashboxes->count(),
            'include_inactive' => $includeInactive,
            'page' => $page,
            'per_page' => $perPage,
            'total' => $cashboxes->total(),
        ]);

        return $cashboxes;
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
