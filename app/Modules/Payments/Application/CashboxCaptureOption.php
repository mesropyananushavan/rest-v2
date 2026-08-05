<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application;

final readonly class CashboxCaptureOption
{
    public function __construct(
        public int $id,
        public string $name,
        public bool $isDefault,
    ) {}
}
