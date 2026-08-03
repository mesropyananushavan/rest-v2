<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Models;

use App\Modules\Tenancy\Contracts\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['tenant_id', 'code', 'name', 'is_management_role'])]
final class Role extends Model
{
    use BelongsToTenant;

    /**
     * @return BelongsToMany<Permission, $this>
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions')
            ->withPivot('tenant_id')
            ->withTimestamps();
    }

    protected function casts(): array
    {
        return [
            'is_management_role' => 'boolean',
        ];
    }
}
