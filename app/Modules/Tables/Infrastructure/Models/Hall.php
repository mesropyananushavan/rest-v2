<?php

declare(strict_types=1);

namespace App\Modules\Tables\Infrastructure\Models;

use App\Modules\Tenancy\Contracts\BelongsToTenant;
use App\Support\I18n\LocalizedText;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['tenant_id', 'branch_id', 'translated_name', 'color', 'sort_order', 'active'])]
final class Hall extends Model
{
    use BelongsToTenant;
    use SoftDeletes;

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
            'translated_name' => 'array',
            'sort_order' => 'integer',
            'active' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }
}
