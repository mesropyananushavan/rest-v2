<?php

declare(strict_types=1);

namespace App\Modules\Payments\Infrastructure\Models;

use App\Modules\Tenancy\Contracts\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable([
    'tenant_id',
    'branch_id',
    'cashbox_id',
    'direction',
    'amount_minor',
    'currency',
    'reason',
    'source_type',
    'source_id',
    'posted_by_id',
])]
final class CashboxEntry extends Model
{
    use BelongsToTenant;

    /**
     * @return BelongsTo<Cashbox, $this>
     */
    public function cashbox(): BelongsTo
    {
        return $this->belongsTo(Cashbox::class, 'cashbox_id');
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function sourcePayment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'source_id');
    }

    protected static function booted(): void
    {
        self::updating(function (): never {
            throw new LogicException('Cashbox entries are append-only and cannot be updated.');
        });

        self::deleting(function (): never {
            throw new LogicException('Cashbox entries are append-only and cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'branch_id' => 'integer',
            'cashbox_id' => 'integer',
            'amount_minor' => 'integer',
            'source_id' => 'integer',
            'posted_by_id' => 'integer',
        ];
    }
}
