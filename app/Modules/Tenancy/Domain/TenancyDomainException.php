<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Domain;

use RuntimeException;

final class TenancyDomainException extends RuntimeException
{
    private function __construct(
        private readonly string $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function unknownTenant(): self
    {
        return new self('tenancy.unknown_tenant', 'The requested tenant does not exist.');
    }

    public static function subscriptionMissing(): self
    {
        return new self('tenancy.subscription_missing', 'The requested tenant does not have a subscription row.');
    }

    public static function staleDueDateConfirmation(): self
    {
        return new self('tenancy.stale_due_date_confirmation', 'The subscription due date changed before the payment was recorded.');
    }

    public static function tenantAlreadyActive(): self
    {
        return new self('tenancy.tenant_already_active', 'The requested tenant is already active.');
    }

    public static function tenantAlreadySuspended(): self
    {
        return new self('tenancy.tenant_already_suspended', 'The requested tenant is already suspended.');
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }
}
