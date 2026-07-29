<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Infrastructure\Models;

use App\Modules\Tenancy\Contracts\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'tenant_id',
    'billing_anchor_day',
    'next_due_on',
    'grace_days',
    'last_paid_on',
])]
final class TenantSubscription extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'billing_anchor_day' => 'integer',
            'next_due_on' => 'date',
            'grace_days' => 'integer',
            'last_paid_on' => 'date',
        ];
    }
}
