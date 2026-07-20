<?php

declare(strict_types=1);

namespace App\Modules\Menu\Infrastructure\Models;

use App\Modules\Tenancy\Contracts\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tenant_id', 'translated_name', 'sort_order', 'active'])]
final class MenuCategory extends Model
{
    use BelongsToTenant;

    /**
     * @return HasMany<MenuItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'category_id');
    }

    /**
     * @return array<string, string>
     */
    public function translatedName(): array
    {
        /** @var array<string, string> $translatedName */
        $translatedName = $this->getAttribute('translated_name') ?? [];

        return $translatedName;
    }

    protected function casts(): array
    {
        return [
            'translated_name' => 'array',
            'sort_order' => 'integer',
            'active' => 'boolean',
        ];
    }
}
