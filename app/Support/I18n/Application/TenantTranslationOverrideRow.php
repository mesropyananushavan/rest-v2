<?php

declare(strict_types=1);

namespace App\Support\I18n\Application;

final readonly class TenantTranslationOverrideRow
{
    /**
     * @param  array<string, string>  $values
     * @param  array<string, string>  $languageValues
     */
    public function __construct(
        public string $key,
        public string $effectiveValue,
        public bool $overridden,
        public array $values,
        public array $languageValues,
    ) {}
}
