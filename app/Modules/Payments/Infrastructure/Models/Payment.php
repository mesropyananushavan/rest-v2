<?php

declare(strict_types=1);

namespace App\Modules\Payments\Infrastructure\Models;

use App\Modules\Tenancy\Contracts\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

#[Fillable([
    'tenant_id',
    'branch_id',
    'order_id',
    'cashbox_id',
    'method',
    'status',
    'amount_minor',
    'currency',
    'idempotency_key',
    'idempotency_fingerprint',
])]
final class Payment extends Model
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
     * @return HasMany<PaymentAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class, 'payment_id')->orderBy('id');
    }

    /**
     * @return HasMany<CashboxEntry, $this>
     */
    public function cashboxEntries(): HasMany
    {
        return $this->hasMany(CashboxEntry::class, 'source_id')
            ->where('source_type', 'payment')
            ->orderBy('id');
    }

    protected static function booted(): void
    {
        self::updating(function (): never {
            throw new LogicException('Payments are append-only and cannot be updated.');
        });

        self::deleting(function (): never {
            throw new LogicException('Payments are append-only and cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'branch_id' => 'integer',
            'order_id' => 'integer',
            'cashbox_id' => 'integer',
            'amount_minor' => 'integer',
        ];
    }
}
