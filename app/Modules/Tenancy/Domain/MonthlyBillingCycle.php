<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Domain;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

final class MonthlyBillingCycle
{
    public function nextDueOn(int $billingAnchorDay, DateTimeInterface $currentDueOn, DateTimeInterface $now): DateTimeImmutable
    {
        $this->assertValidAnchorDay($billingAnchorDay);

        // The caller supplies time context explicitly, but payment timing must not move the anchor.
        $this->dateOnly($now);

        $targetMonth = $this->dateOnly($currentDueOn)->modify('first day of next month');
        $targetDay = min($billingAnchorDay, (int) $targetMonth->format('t'));

        return $targetMonth
            ->setDate((int) $targetMonth->format('Y'), (int) $targetMonth->format('m'), $targetDay)
            ->setTime(0, 0);
    }

    private function assertValidAnchorDay(int $billingAnchorDay): void
    {
        if ($billingAnchorDay < 1 || $billingAnchorDay > 31) {
            throw new InvalidArgumentException('Billing anchor day must be between 1 and 31.');
        }
    }

    private function dateOnly(DateTimeInterface $date): DateTimeImmutable
    {
        return DateTimeImmutable::createFromInterface($date)->setTime(0, 0);
    }
}
