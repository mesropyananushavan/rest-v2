<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Infrastructure\Models;

use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'slug', 'default_locale', 'currency', 'status'])]
final class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory;
}
