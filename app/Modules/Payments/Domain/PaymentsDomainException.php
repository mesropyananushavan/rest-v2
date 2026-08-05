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

    public static function actorContextRequired(): self
    {
        return new self('payments.actor_context_required', 'Payment capture requires an authenticated actor.');
    }

    public static function captureAmountMustBePositive(): self
    {
        return new self('payments.capture_amount_must_be_positive', 'Payment capture amount must be positive.');
    }

    public static function captureCurrencyInvalid(): self
    {
        return new self('payments.capture_currency_invalid', 'Payment capture currency must be a three-letter uppercase ISO code.');
    }

    public static function idempotencyKeyRequired(): self
    {
        return new self('payments.idempotency_key_required', 'Payment capture idempotency key is required.');
    }

    public static function idempotencyKeyTooLong(): self
    {
        return new self('payments.idempotency_key_too_long', 'Payment capture idempotency key is too long.');
    }

    public static function idempotencyKeyWhitespace(): self
    {
        return new self('payments.idempotency_key_whitespace', 'Payment capture idempotency key cannot start or end with whitespace.');
    }

    public static function idempotencyKeyControlCharacters(): self
    {
        return new self('payments.idempotency_key_control_characters', 'Payment capture idempotency key cannot contain control characters.');
    }

    public static function idempotencyConflict(): self
    {
        return new self('payments.idempotency_conflict', 'This idempotency key was already used with different payment capture input.');
    }

    public static function cashboxUnavailable(): self
    {
        return new self('payments.cashbox_unavailable', 'The selected cashbox is not available for payment capture.');
    }

    public static function expectedAmountMismatch(): self
    {
        return new self('payments.expected_amount_mismatch', 'The expected payment amount no longer matches the remaining order balance.');
    }

    public static function expectedCurrencyMismatch(): self
    {
        return new self('payments.expected_currency_mismatch', 'The expected payment currency no longer matches the order currency.');
    }

    public static function orderAlreadyFullyPaid(): self
    {
        return new self('payments.order_already_fully_paid', 'This order is already fully paid.');
    }

    public static function orderOverAllocated(): self
    {
        return new self('payments.order_over_allocated', 'Captured payment allocations exceed the order total.');
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }
}
