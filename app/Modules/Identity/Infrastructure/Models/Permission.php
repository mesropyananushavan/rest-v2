<?php

namespace App\Modules\Identity\Infrastructure\Models;

use App\Modules\Tenancy\Contracts\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['tenant_id', 'code', 'name'])]
final class Permission extends Model
{
    use BelongsToTenant;
}
