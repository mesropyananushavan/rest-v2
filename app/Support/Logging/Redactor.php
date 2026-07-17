<?php

declare(strict_types=1);

namespace App\Support\Logging;

final class Redactor
{
    private const REDACTED = '[redacted]';

    /**
     * @var list<string>
     */
    private const SENSITIVE_KEYS = [
        'authorization',
        'card_number',
        'cvv',
        'idempotency_secret',
        'password',
        'password_confirmation',
        'pin',
        'refresh_token',
        'secret',
        'token',
    ];

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public static function context(array $context): array
    {
        return self::redactArray($context);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private static function redactArray(array $values): array
    {
        foreach ($values as $key => $value) {
            if (self::isSensitive((string) $key)) {
                $values[$key] = self::REDACTED;

                continue;
            }

            if (is_array($value)) {
                /** @var array<string, mixed> $value */
                $values[$key] = self::redactArray($value);
            }
        }

        return $values;
    }

    private static function isSensitive(string $key): bool
    {
        $normalized = strtolower(str_replace(['-', ' '], '_', $key));

        foreach (self::SENSITIVE_KEYS as $sensitiveKey) {
            if ($normalized === $sensitiveKey || str_ends_with($normalized, "_{$sensitiveKey}")) {
                return true;
            }
        }

        return false;
    }
}
