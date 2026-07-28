<?php

declare(strict_types=1);

namespace App\Support\Audit\Application;

use Carbon\CarbonImmutable;
use stdClass;

final readonly class AuditLogReportRow
{
    public function __construct(
        public int $id,
        public string $createdAt,
        public ?int $branchId,
        public ?string $branchName,
        public ?int $actorId,
        public ?string $actorName,
        public string $action,
        public string $targetType,
        public int $targetId,
        public string $correlationId,
        public ?string $ipAddress,
        public ?string $beforeJson,
        public ?string $afterJson,
    ) {}

    public static function fromDatabaseRow(stdClass $row, string $timezone): self
    {
        return new self(
            id: self::intValue($row->id ?? null),
            createdAt: CarbonImmutable::parse(self::stringValue($row->created_at ?? null), 'UTC')
                ->setTimezone($timezone)
                ->format('Y-m-d H:i:s T'),
            branchId: is_numeric($row->branch_id ?? null) ? (int) $row->branch_id : null,
            branchName: is_string($row->branch_name) ? $row->branch_name : null,
            actorId: is_numeric($row->actor_id ?? null) ? (int) $row->actor_id : null,
            actorName: is_string($row->actor_name) ? $row->actor_name : null,
            action: self::stringValue($row->action ?? null),
            targetType: self::stringValue($row->target_type ?? null),
            targetId: self::intValue($row->target_id ?? null),
            correlationId: self::stringValue($row->correlation_id ?? null),
            ipAddress: is_string($row->ip_address) ? $row->ip_address : null,
            beforeJson: self::prettyJson($row->before_json ?? null),
            afterJson: self::prettyJson($row->after_json ?? null),
        );
    }

    public function actorLabel(): string
    {
        if ($this->actorId === null) {
            return __('admin.audit_logs.values.system_actor');
        }

        if ($this->actorName === null || $this->actorName === '') {
            return __('admin.audit_logs.values.unknown_actor', ['id' => $this->actorId]);
        }

        return "{$this->actorName} #{$this->actorId}";
    }

    public function branchLabel(): string
    {
        if ($this->branchId === null) {
            return __('admin.audit_logs.values.no_branch');
        }

        if ($this->branchName === null || $this->branchName === '') {
            return __('admin.audit_logs.values.unknown_branch', ['id' => $this->branchId]);
        }

        return "{$this->branchName} #{$this->branchId}";
    }

    private static function prettyJson(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $decoded = is_string($value) ? json_decode($value, associative: true) : $value;

        if (json_last_error() !== JSON_ERROR_NONE) {
            return is_string($value) ? $value : null;
        }

        $encoded = json_encode(
            $decoded,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );

        return is_string($encoded) ? $encoded : null;
    }

    private static function intValue(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private static function stringValue(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
