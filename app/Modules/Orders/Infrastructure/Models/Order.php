<?php

declare(strict_types=1);

namespace App\Modules\Orders\Infrastructure\Models;

use App\Modules\Tenancy\Contracts\BelongsToTenant;
use App\Support\Money\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use UnexpectedValueException;

#[Fillable([
    'tenant_id',
    'branch_id',
    'type',
    'status',
    'table_id',
    'customer_id',
    'waiter_id',
    'cashier_id',
    'opened_at',
    'closed_at',
    'client_count',
    'comment',
    'subtotal_minor',
    'discount_minor',
    'total_minor',
    'currency',
])]
final class Order extends Model
{
    use BelongsToTenant;

    /**
     * @return HasMany<OrderSubtable, $this>
     */
    public function subtables(): HasMany
    {
        return $this->hasMany(OrderSubtable::class, 'order_id')->orderBy('id');
    }

    public function subtotal(): Money
    {
        return $this->moneyFrom('subtotal_minor');
    }

    public function discount(): Money
    {
        return $this->moneyFrom('discount_minor');
    }

    public function total(): Money
    {
        return $this->moneyFrom('total_minor');
    }

    protected function casts(): array
    {
        return [
            'branch_id' => 'integer',
            'table_id' => 'integer',
            'customer_id' => 'integer',
            'waiter_id' => 'integer',
            'cashier_id' => 'integer',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'client_count' => 'integer',
            'subtotal_minor' => 'integer',
            'discount_minor' => 'integer',
            'total_minor' => 'integer',
        ];
    }

    private function moneyFrom(string $attribute): Money
    {
        $minor = $this->getAttribute($attribute);
        $currency = $this->getAttribute('currency');

        if (! is_int($minor) || ! is_string($currency)) {
            throw new UnexpectedValueException('Order money attributes are not hydrated.');
        }

        return new Money($minor, $currency);
    }
}
