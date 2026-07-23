<?php

declare(strict_types=1);

namespace App\Modules\Tables\Domain;

use RuntimeException;

final class TablesDomainException extends RuntimeException
{
    private function __construct(
        private readonly string $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function branchContextRequired(): self
    {
        return new self('tables.branch_context_required', 'Hall operations require a resolved branch context.');
    }

    public static function restoreHallFirst(): self
    {
        return new self('tables.restore_hall_first', 'Restore the hall before restoring this table.');
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }
}
