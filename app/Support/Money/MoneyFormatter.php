<?php

declare(strict_types=1);

namespace App\Support\Money;

use InvalidArgumentException;

final class MoneyFormatter
{
    private const int MINOR_FACTOR = 100;

    /**
     * @var array<string, string>
     */
    private const array SYMBOLS = [
        'AMD' => '֏',
        'EUR' => '€',
        'RUB' => '₽',
        'USD' => '$',
    ];

    /**
     * @var array<string, bool>
     */
    private const array PREFIX_SYMBOLS = [
        'EUR' => true,
        'USD' => true,
    ];

    public static function format(Money $money, string $locale): string
    {
        $amount = self::localizedMajor(self::toMajor($money), $locale);
        $symbol = self::symbol($money->currency);

        if (self::PREFIX_SYMBOLS[$money->currency] ?? false) {
            return $symbol.$amount;
        }

        return $amount.' '.$symbol;
    }

    public static function toMajor(Money $money): string
    {
        $whole = intdiv($money->minor, self::MINOR_FACTOR);
        $fraction = $money->minor % self::MINOR_FACTOR;

        if ($money->currency === 'AMD' && $fraction === 0) {
            return (string) $whole;
        }

        return $whole.'.'.str_pad((string) $fraction, 2, '0', STR_PAD_LEFT);
    }

    public static function minorFromMajor(string $major, string $currency): int
    {
        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new InvalidArgumentException('Money currency must be an ISO 4217 code.');
        }

        $normalized = str_replace(',', '.', trim($major));

        if (preg_match('/^\d+(\.\d{1,2})?$/', $normalized) !== 1) {
            throw new InvalidArgumentException('Money major amount must be a positive decimal string with up to two fractional digits.');
        }

        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '0');

        return ((int) $whole * self::MINOR_FACTOR) + (int) str_pad($fraction, 2, '0', STR_PAD_RIGHT);
    }

    private static function localizedMajor(string $major, string $locale): string
    {
        if (! str_contains($major, '.')) {
            return $major;
        }

        return str_replace('.', self::decimalSeparator($locale), $major);
    }

    private static function decimalSeparator(string $locale): string
    {
        return str_starts_with($locale, 'en') ? '.' : ',';
    }

    private static function symbol(string $currency): string
    {
        return self::SYMBOLS[$currency] ?? $currency;
    }
}
