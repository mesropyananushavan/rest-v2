<?php

declare(strict_types=1);

namespace App\Modules\Orders\Infrastructure\Models;

use App\Modules\Tenancy\Contracts\BelongsToTenant;
use App\Support\Money\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use UnexpectedValueException;

#[Fillable([
    'tenant_id',
    'branch_id',
    'order_id',
    'subtable_id',
    'menu_item_id',
    'menu_item_name_snapshot',
    'qty',
    'unit_price_minor',
    'discount_minor',
    'total_minor',
    'currency',
    'seller_id',
    'preparation_status',
])]
final class OrderItem extends Model
{
    use BelongsToTenant;

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    /**
     * @return BelongsTo<OrderSubtable, $this>
     */
    public function subtable(): BelongsTo
    {
        return $this->belongsTo(OrderSubtable::class, 'subtable_id');
    }

    public function unitPrice(): Money
    {
        return $this->moneyFrom('unit_price_minor');
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
            'order_id' => 'integer',
            'subtable_id' => 'integer',
            'menu_item_id' => 'integer',
            'menu_item_name_snapshot' => 'array',
            'qty' => 'integer',
            'unit_price_minor' => 'integer',
            'discount_minor' => 'integer',
            'total_minor' => 'integer',
            'seller_id' => 'integer',
        ];
    }

    private function moneyFrom(string $attribute): Money
    {
        $minor = $this->getAttribute($attribute);
        $currency = $this->getAttribute('currency');

        if (! is_int($minor) || ! is_string($currency)) {
            throw new UnexpectedValueException('Order item money attributes are not hydrated.');
        }

        return new Money($minor, $currency);
    }
}
