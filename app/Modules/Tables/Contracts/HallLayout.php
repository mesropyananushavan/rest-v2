<?php

declare(strict_types=1);

namespace App\Modules\Tables\Contracts;

use App\Support\I18n\LocalizedText;

final readonly class HallLayout
{
    /**
     * @param  list<TableLayout>  $tables
     */
    public function __construct(
        public int $id,
        public int $branchId,
        public LocalizedText $name,
        public string $color,
        public int $sortOrder,
        public array $tables,
    ) {}
}
