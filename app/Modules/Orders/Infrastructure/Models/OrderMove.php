<?php

declare(strict_types=1);

namespace App\Modules\Orders\Infrastructure\Models;

use App\Modules\Tenancy\Contracts\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'branch_id',
    'order_id',
    'source_table_id',
    'target_table_id',
    'actor_id',
    'reason',
])]
final class OrderMove extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'branch_id' => 'integer',
            'order_id' => 'integer',
            'source_table_id' => 'integer',
            'target_table_id' => 'integer',
            'actor_id' => 'integer',
        ];
    }
}
