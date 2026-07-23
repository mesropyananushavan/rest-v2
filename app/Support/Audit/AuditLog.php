<?php

declare(strict_types=1);

namespace App\Support\Audit;

use App\Modules\Tenancy\Contracts\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use LogicException;

#[Fillable([
    'tenant_id',
    'branch_id',
    'actor_id',
    'action',
    'target_type',
    'target_id',
    'before_json',
    'after_json',
    'correlation_id',
    'ip_address',
])]
final class AuditLog extends Model
{
    use BelongsToTenant;

    protected static function booted(): void
    {
        self::updating(function (): never {
            throw new LogicException('Audit logs are append-only and cannot be updated.');
        });

        self::deleting(function (): never {
            throw new LogicException('Audit logs are append-only and cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return [
            'branch_id' => 'integer',
            'actor_id' => 'integer',
            'target_id' => 'integer',
            'before_json' => 'array',
            'after_json' => 'array',
        ];
    }
}
