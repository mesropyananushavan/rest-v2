<?php

declare(strict_types=1);

namespace App\Modules\Menu\Infrastructure\Models;

use App\Modules\Tenancy\Contracts\BelongsToTenant;
use App\Support\I18n\LocalizedText;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['tenant_id', 'parent_id', 'archived_with_category_id', 'translated_name', 'sort_order', 'active'])]
final class MenuCategory extends Model
{
    use BelongsToTenant;
    use SoftDeletes;

    /**
     * @return BelongsTo<MenuCategory, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id')->withTrashed();
    }

    /**
     * @return HasMany<MenuCategory, $this>
     */
    public function subcategories(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return BelongsTo<MenuCategory, $this>
     */
    public function archivedWithCategory(): BelongsTo
    {
        return $this->belongsTo(self::class, 'archived_with_category_id')->withTrashed();
    }

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
            'parent_id' => 'integer',
            'archived_with_category_id' => 'integer',
            'sort_order' => 'integer',
            'active' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }
}
