<?php

declare(strict_types=1);

namespace App\Support\I18n;

use RuntimeException;

final class TenantTranslationOverrideException extends RuntimeException
{
    private function __construct(
        private readonly string $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function tenantContextRequired(): self
    {
        return new self('admin.translation_overrides.errors.tenant_context_required', 'Tenant translation override writes require a tenant context.');
    }

    public static function invalidLocale(string $locale): self
    {
        return new self('admin.translation_overrides.errors.invalid_locale', "Unsupported tenant translation override locale [{$locale}].");
    }

    public static function translationKeyMissing(string $key): self
    {
        return new self('admin.translation_overrides.errors.translation_key_missing', "Tenant translation override key [{$key}] does not exist in language files.");
    }

    public static function keyNotOverridable(string $key): self
    {
        return new self('admin.translation_overrides.errors.key_not_overridable', "Tenant translation override key [{$key}] is not overridable.");
    }

    public static function valueTooLong(): self
    {
        return new self('admin.translation_overrides.errors.value_too_long', 'Tenant translation override value exceeds the maximum length.');
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }
}
