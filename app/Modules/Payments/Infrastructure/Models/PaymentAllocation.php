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
    'payment_id',
    'payable_type',
    'payable_id',
    'amount_minor',
    'currency',
])]
final class PaymentAllocation extends Model
{
    use BelongsToTenant;

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }

    protected static function booted(): void
    {
        self::updating(function (): never {
            throw new LogicException('Payment allocations are append-only and cannot be updated.');
        });

        self::deleting(function (): never {
            throw new LogicException('Payment allocations are append-only and cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'branch_id' => 'integer',
            'payment_id' => 'integer',
            'payable_id' => 'integer',
            'amount_minor' => 'integer',
        ];
    }
}
