<?php

declare(strict_types=1);

namespace App\Support\Audit\Application;

use App\Modules\Tenancy\Contracts\TenantResolver;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use stdClass;

final class BrowseAuditLogs
{
    public const DEFAULT_WINDOW_DAYS = 7;

    public const MAX_WINDOW_DAYS = 31;

    private const PER_PAGE = 25;

    public function __construct(
        private readonly TenantResolver $tenants,
    ) {}

    /**
     * @return LengthAwarePaginator<int, AuditLogReportRow>
     */
    public function paginate(AuditLogReportFilters $filters, string $timezone, int $page = 1): LengthAwarePaginator
    {
        /** @var LengthAwarePaginator<int, stdClass> $logs */
        $logs = $this->baseQuery($filters)
            ->select($this->listColumns())
            ->orderByDesc('audit_logs.created_at')
            ->orderByDesc('audit_logs.id')
            ->paginate(self::PER_PAGE, ['*'], 'page', max(1, $page));

        /** @var LengthAwarePaginator<int, AuditLogReportRow> $rows */
        $rows = $logs->through(
            fn (stdClass $row): AuditLogReportRow => AuditLogReportRow::fromDatabaseRow($row, $timezone),
        );

        return $rows;
    }

    public function find(int $auditLogId, AuditLogReportFilters $filters, string $timezone): ?AuditLogReportRow
    {
        /** @var stdClass|null $row */
        $row = $this->baseQuery($filters)
            ->select($this->detailColumns())
            ->where('audit_logs.id', $auditLogId)
            ->first();

        return $row === null ? null : AuditLogReportRow::fromDatabaseRow($row, $timezone);
    }

    private function baseQuery(AuditLogReportFilters $filters): Builder
    {
        $tenantId = $this->tenants->id();

        if ($tenantId === null) {
            throw new RuntimeException('Audit log reporting requires a tenant context.');
        }

        $query = DB::table('audit_logs')
            ->leftJoin('users as actors', function (JoinClause $join): void {
                $join->on('actors.id', '=', 'audit_logs.actor_id')
                    ->whereColumn('actors.tenant_id', 'audit_logs.tenant_id');
            })
            ->leftJoin('branches', function (JoinClause $join): void {
                $join->on('branches.id', '=', 'audit_logs.branch_id')
                    ->whereColumn('branches.tenant_id', 'audit_logs.tenant_id');
            })
            ->where('audit_logs.tenant_id', $tenantId)
            ->whereBetween('audit_logs.created_at', [$filters->fromUtc, $filters->toUtc]);

        $branchScope = $filters->branchScope();

        if ($branchScope === []) {
            $query->whereRaw('1 = 0');
        } else {
            $query->whereIn('audit_logs.branch_id', $branchScope);
        }

        if ($filters->actorId !== null) {
            $query->where('audit_logs.actor_id', $filters->actorId);
        }

        if ($filters->action !== null) {
            $query->where('audit_logs.action', $filters->action);
        }

        if ($filters->targetType !== null) {
            $query->where('audit_logs.target_type', $filters->targetType);
        }

        return $query;
    }

    /**
     * @return list<string>
     */
    private function listColumns(): array
    {
        return [
            'audit_logs.id',
            'audit_logs.created_at',
            'audit_logs.branch_id',
            'branches.name as branch_name',
            'audit_logs.actor_id',
            'actors.name as actor_name',
            'audit_logs.action',
            'audit_logs.target_type',
            'audit_logs.target_id',
            'audit_logs.correlation_id',
            'audit_logs.ip_address',
        ];
    }

    /**
     * @return list<string>
     */
    private function detailColumns(): array
    {
        return [
            ...$this->listColumns(),
            'audit_logs.before_json',
            'audit_logs.after_json',
        ];
    }
}
