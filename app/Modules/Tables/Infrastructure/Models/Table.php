<?php

declare(strict_types=1);

namespace App\Modules\Tables\Infrastructure\Models;

use App\Modules\Tenancy\Contracts\BelongsToTenant;
use App\Support\I18n\LocalizedText;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'tenant_id',
    'branch_id',
    'hall_id',
    'archived_with_hall_id',
    'translated_name',
    'type',
    'shape',
    'hdm_department',
    'is_delivery',
    'sort_order',
    'active',
])]
final class Table extends Model
{
    use BelongsToTenant;
    use SoftDeletes;

    /**
     * @return BelongsTo<Hall, $this>
     */
    public function hall(): BelongsTo
    {
        return $this->belongsTo(Hall::class, 'hall_id')->withTrashed();
    }

    /**
     * @return BelongsTo<Hall, $this>
     */
    public function archivedWithHall(): BelongsTo
    {
        return $this->belongsTo(Hall::class, 'archived_with_hall_id')->withTrashed();
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
            'branch_id' => 'integer',
            'hall_id' => 'integer',
            'archived_with_hall_id' => 'integer',
            'translated_name' => 'array',
            'hdm_department' => 'integer',
            'is_delivery' => 'boolean',
            'sort_order' => 'integer',
            'active' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }
}
