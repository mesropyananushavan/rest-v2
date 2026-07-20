<?php

declare(strict_types=1);

namespace App\Modules\Menu\Infrastructure\Models;

use App\Modules\Tenancy\Contracts\BelongsToTenant;
use App\Support\I18n\LocalizedText;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['tenant_id', 'translated_name', 'sort_order', 'active'])]
final class MenuCategory extends Model
{
    use BelongsToTenant;
    use SoftDeletes;

    /**
     * @return HasMany<MenuItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'category_id');
    }

    public function translatedName(): LocalizedText
    {
        /** @var array<string, mixed> $translatedName */
        $translatedName = $this->getAttribute('translated_name') ?? [];

        return LocalizedText::fromArray($translatedName);
    }

    protected function casts(): array
    {
        return [
            'translated_name' => 'array',
            'sort_order' => 'integer',
            'active' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }
}
