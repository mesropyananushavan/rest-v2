<?php

declare(strict_types=1);

namespace App\Support\Audit\Application;

use Carbon\CarbonImmutable;

final readonly class AuditLogReportFilters
{
    /**
     * @param  list<int>  $visibleBranchIds
     */
    public function __construct(
        public string $dateFrom,
        public string $dateTo,
        public CarbonImmutable $fromUtc,
        public CarbonImmutable $toUtc,
        public array $visibleBranchIds,
        public ?int $actorId = null,
        public ?string $action = null,
        public ?string $targetType = null,
        public ?int $branchId = null,
    ) {}

    /**
     * @return array<string, int|string>
     */
    public function queryParameters(): array
    {
        return array_filter([
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'actor_id' => $this->actorId,
            'action' => $this->action,
            'target_type' => $this->targetType,
            'branch_id' => $this->branchId,
        ], fn (int|string|null $value): bool => $value !== null && $value !== '');
    }

    /**
     * @return list<int>
     */
    public function branchScope(): array
    {
        if ($this->branchId !== null) {
            return [$this->branchId];
        }

        return $this->visibleBranchIds;
    }
}
