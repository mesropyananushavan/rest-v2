<?php

declare(strict_types=1);

namespace App\Modules\Payments\Domain;

use RuntimeException;

final class PaymentsDomainException extends RuntimeException
{
    private function __construct(
        private readonly string $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function tenantContextRequired(): self
    {
        return new self('payments.tenant_context_required', 'Cashbox operations require a resolved tenant context.');
    }

    public static function branchContextRequired(): self
    {
        return new self('payments.branch_context_required', 'Cashbox operations require a resolved branch context.');
    }

    public static function cashboxNameRequired(): self
    {
        return new self('payments.cashbox_name_required', 'Cashbox name is required.');
    }

    public static function cashboxNameTooLong(): self
    {
        return new self('payments.cashbox_name_too_long', 'Cashbox name is too long.');
    }

    public static function cashboxNameDuplicate(): self
    {
        return new self('payments.cashbox_name_duplicate', 'An active cashbox with this name already exists in this branch.');
    }

    public static function defaultReplacementRequired(): self
    {
        return new self('payments.cashbox_default_replacement_required', 'Choose another active default cashbox before deactivating this one.');
    }

    public static function replacementCashboxInvalid(): self
    {
        return new self('payments.cashbox_replacement_invalid', 'The replacement default cashbox must be active in this branch.');
    }

    public static function defaultCashboxMustBeActive(): self
    {
        return new self('payments.cashbox_default_must_be_active', 'Only an active cashbox can be selected as default.');
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }
}
