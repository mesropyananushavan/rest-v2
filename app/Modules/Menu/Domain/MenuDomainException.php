<?php

declare(strict_types=1);

namespace App\Modules\Menu\Domain;

use RuntimeException;

final class MenuDomainException extends RuntimeException
{
    private function __construct(
        private readonly string $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function branchContextRequired(): self
    {
        return new self('menu.branch_context_required', 'Menu item operations require a resolved branch context.');
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }
}
