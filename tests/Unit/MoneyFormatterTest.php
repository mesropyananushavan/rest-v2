<?php

declare(strict_types=1);

use App\Support\Money\Money;
use App\Support\Money\MoneyFormatter;

it('formats AMD minor units as major drams with the dram symbol', function (): void {
    $money = new Money(220000, 'AMD');

    expect(MoneyFormatter::toMajor($money))->toBe('2200')
        ->and(MoneyFormatter::format($money, 'hy'))->toBe('2200 ֏')
        ->and(MoneyFormatter::minorFromMajor('2200', 'AMD'))->toBe(220000);
});

it('formats decimal currencies with locale decimal separators', function (): void {
    $money = new Money(1499, 'USD');

    expect(MoneyFormatter::toMajor($money))->toBe('14.99')
        ->and(MoneyFormatter::format($money, 'en'))->toBe('$14.99')
        ->and(MoneyFormatter::format($money, 'ru'))->toBe('$14,99')
        ->and(MoneyFormatter::minorFromMajor('14,99', 'USD'))->toBe(1499);
});

it('rejects invalid major-unit money input', function (): void {
    MoneyFormatter::minorFromMajor('14.999', 'USD');
})->throws(InvalidArgumentException::class);
