<?php

declare(strict_types=1);

namespace App\Modules\Orders\Infrastructure\Models;

use App\Modules\Tenancy\Contracts\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'tenant_id',
    'branch_id',
    'order_item_id',
    'source_order_id',
    'target_order_id',
    'source_subtable_id',
    'target_subtable_id',
    'actor_id',
    'reason',
])]
final class OrderItemMove extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'branch_id' => 'integer',
            'order_item_id' => 'integer',
            'source_order_id' => 'integer',
            'target_order_id' => 'integer',
            'source_subtable_id' => 'integer',
            'target_subtable_id' => 'integer',
            'actor_id' => 'integer',
        ];
    }
}
