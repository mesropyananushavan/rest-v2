<?php

declare(strict_types=1);

namespace App\Modules\Menu\Application;

final class DeleteMenuItem
{
    public function __construct(
        private readonly ArchiveMenuItem $archive,
    ) {}

    public function __invoke(int $itemId): void
    {
        ($this->archive)($itemId);
    }
}
