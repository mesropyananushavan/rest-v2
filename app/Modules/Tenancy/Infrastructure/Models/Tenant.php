<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Infrastructure\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'slug', 'default_locale', 'currency', 'status'])]
final class Tenant extends Model
{
    //
}
