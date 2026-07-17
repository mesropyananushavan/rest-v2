<?php

namespace App\Modules\Tenancy\Infrastructure\Models;

use App\Modules\Tenancy\Contracts\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['tenant_id', 'name', 'address', 'phone', 'locale', 'timezone', 'status'])]
final class Branch extends Model
{
    use BelongsToTenant;
}
