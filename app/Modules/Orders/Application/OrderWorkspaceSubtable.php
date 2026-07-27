<?php

declare(strict_types=1);

namespace App\Modules\Orders\Application;

final readonly class OrderWorkspaceSubtable
{
    public function __construct(
        public int $id,
        public string $name,
    ) {}
}
