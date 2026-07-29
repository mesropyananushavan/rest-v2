<?php

declare(strict_types=1);

use App\Modules\Tenancy\Domain\MonthlyBillingCycle;

it('advances anchor day one through regular months and across years', function (): void {
    $cycle = new MonthlyBillingCycle;

    $february = $cycle->nextDueOn(1, dateOnly('2026-01-01'), nowAt('2026-01-10'));
    $march = $cycle->nextDueOn(1, $february, nowAt('2026-02-10'));
    $january = $cycle->nextDueOn(1, dateOnly('2026-12-01'), nowAt('2026-12-10'));

    expectDate($february, '2026-02-01');
    expectDate($march, '2026-03-01');
    expectDate($january, '2027-01-01');
});

it('clamps anchor day thirty one in February without drifting in March', function (): void {
    $cycle = new MonthlyBillingCycle;

    $february = $cycle->nextDueOn(31, dateOnly('2026-01-31'), nowAt('2026-01-31'));
    $march = $cycle->nextDueOn(31, $february, nowAt('2026-02-28'));

    expectDate($february, '2026-02-28');
    expectDate($march, '2026-03-31');
});

it('clamps anchor day thirty one to leap day and then returns to thirty one', function (): void {
    $cycle = new MonthlyBillingCycle;

    $february = $cycle->nextDueOn(31, dateOnly('2024-01-31'), nowAt('2024-01-31'));
    $march = $cycle->nextDueOn(31, $february, nowAt('2024-02-29'));

    expectDate($february, '2024-02-29');
    expectDate($march, '2024-03-31');
});

it('clamps anchor day thirty in February and returns to thirty in March', function (): void {
    $cycle = new MonthlyBillingCycle;

    $february = $cycle->nextDueOn(30, dateOnly('2026-01-30'), nowAt('2026-01-30'));
    $march = $cycle->nextDueOn(30, $february, nowAt('2026-02-28'));
    $leapFebruary = $cycle->nextDueOn(30, dateOnly('2024-01-30'), nowAt('2024-01-30'));
    $leapMarch = $cycle->nextDueOn(30, $leapFebruary, nowAt('2024-02-29'));

    expectDate($february, '2026-02-28');
    expectDate($march, '2026-03-30');
    expectDate($leapFebruary, '2024-02-29');
    expectDate($leapMarch, '2024-03-30');
});

it('handles anchor day twenty nine in leap and non leap years', function (): void {
    $cycle = new MonthlyBillingCycle;

    $nonLeapFebruary = $cycle->nextDueOn(29, dateOnly('2026-01-29'), nowAt('2026-01-29'));
    $nonLeapMarch = $cycle->nextDueOn(29, $nonLeapFebruary, nowAt('2026-02-28'));
    $leapFebruary = $cycle->nextDueOn(29, dateOnly('2024-01-29'), nowAt('2024-01-29'));
    $leapMarch = $cycle->nextDueOn(29, $leapFebruary, nowAt('2024-02-29'));

    expectDate($nonLeapFebruary, '2026-02-28');
    expectDate($nonLeapMarch, '2026-03-29');
    expectDate($leapFebruary, '2024-02-29');
    expectDate($leapMarch, '2024-03-29');
});

it('preserves the anchor while advancing several months from clamped dates', function (): void {
    $cycle = new MonthlyBillingCycle;
    $due = dateOnly('2026-01-31');
    $dates = [];

    foreach (['2026-01-31', '2026-02-28', '2026-03-31', '2026-04-30', '2026-05-31'] as $now) {
        $due = $cycle->nextDueOn(31, $due, nowAt($now));
        $dates[] = $due->format('Y-m-d');
    }

    expect($dates)->toBe([
        '2026-02-28',
        '2026-03-31',
        '2026-04-30',
        '2026-05-31',
        '2026-06-30',
    ]);
});

it('rejects invalid anchor days', function (): void {
    $cycle = new MonthlyBillingCycle;

    expect(fn () => $cycle->nextDueOn(0, dateOnly('2026-01-01'), nowAt('2026-01-01')))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => $cycle->nextDueOn(32, dateOnly('2026-01-01'), nowAt('2026-01-01')))
        ->toThrow(InvalidArgumentException::class);
});

function dateOnly(string $date): DateTimeImmutable
{
    return new DateTimeImmutable($date.' 00:00:00 Asia/Yerevan');
}

function nowAt(string $date): DateTimeImmutable
{
    return new DateTimeImmutable($date.' 12:00:00 Asia/Yerevan');
}

function expectDate(DateTimeImmutable $date, string $expected): void
{
    expect($date->format('Y-m-d'))->toBe($expected)
        ->and($date->format('H:i:s'))->toBe('00:00:00');
}
