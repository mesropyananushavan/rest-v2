<?php

declare(strict_types=1);

namespace App\Modules\Menu\Infrastructure\Models;

use App\Modules\Tenancy\Contracts\BelongsToTenant;
use App\Support\I18n\LocalizedText;
use App\Support\Money\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use UnexpectedValueException;

#[Fillable([
    'tenant_id',
    'branch_id',
    'category_id',
    'translated_name',
    'translated_description',
    'internal_image',
    'public_image',
    'price_minor',
    'currency',
    'sort_order',
    'active',
    'archived_with_category_id',
])]
#[Hidden(['load_test_key'])]
final class MenuItem extends Model
{
    use BelongsToTenant;
    use SoftDeletes;

    /**
     * @return BelongsTo<MenuCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class, 'category_id')->withTrashed();
    }

    public function price(): Money
    {
        $minor = $this->getAttribute('price_minor');
        $currency = $this->getAttribute('currency');

        if (! is_int($minor) || ! is_string($currency)) {
            throw new UnexpectedValueException('Menu item price attributes are not hydrated.');
        }

        return new Money($minor, $currency);
    }

    public function translatedName(): LocalizedText
    {
        /** @var array<string, mixed> $translatedName */
        $translatedName = $this->getAttribute('translated_name') ?? [];

        return LocalizedText::fromArray($translatedName);
    }

    public function translatedDescription(): ?LocalizedText
    {
        $translatedDescription = $this->getAttribute('translated_description');

        if ($translatedDescription === null) {
            return null;
        }

        /** @var array<string, mixed> $translatedDescription */

        return LocalizedText::fromArray($translatedDescription);
    }

    protected function casts(): array
    {
        return [
            'translated_name' => 'array',
            'translated_description' => 'array',
            'internal_image' => 'array',
            'public_image' => 'array',
            'price_minor' => 'integer',
            'sort_order' => 'integer',
            'active' => 'boolean',
            'archived_with_category_id' => 'integer',
            'deleted_at' => 'datetime',
        ];
    }
}
