<?php

declare(strict_types=1);

namespace App\Modules\Menu\Application;

final class DeleteMenuCategory
{
    public function __construct(
        private readonly ArchiveMenuCategory $archive,
    ) {}

    public function __invoke(int $categoryId): void
    {
        ($this->archive)($categoryId);
    }
}
