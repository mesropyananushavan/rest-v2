<?php

declare(strict_types=1);

namespace App\Modules\Tables\Contracts;

use App\Support\I18n\LocalizedText;

final readonly class TableLayout
{
    public function __construct(
        public int $id,
        public int $branchId,
        public int $hallId,
        public LocalizedText $name,
        public string $type,
        public string $shape,
        public int $sortOrder,
    ) {}
}
