<?php

declare(strict_types=1);

namespace App\Modules\Menu\Infrastructure\Models;

use App\Modules\Tenancy\Contracts\BelongsToTenant;
use App\Support\Money\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use UnexpectedValueException;

#[Fillable([
    'tenant_id',
    'branch_id',
    'category_id',
    'translated_name',
    'translated_description',
    'price_minor',
    'currency',
    'sort_order',
    'active',
])]
final class MenuItem extends Model
{
    use BelongsToTenant;

    /**
     * @return BelongsTo<MenuCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class, 'category_id');
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

    /**
     * @return array<string, string>
     */
    public function translatedName(): array
    {
        /** @var array<string, string> $translatedName */
        $translatedName = $this->getAttribute('translated_name') ?? [];

        return $translatedName;
    }

    /**
     * @return array<string, string>
     */
    public function translatedDescription(): array
    {
        /** @var array<string, string> $translatedDescription */
        $translatedDescription = $this->getAttribute('translated_description') ?? [];

        return $translatedDescription;
    }

    protected function casts(): array
    {
        return [
            'translated_name' => 'array',
            'translated_description' => 'array',
            'price_minor' => 'integer',
            'sort_order' => 'integer',
            'active' => 'boolean',
        ];
    }
}
