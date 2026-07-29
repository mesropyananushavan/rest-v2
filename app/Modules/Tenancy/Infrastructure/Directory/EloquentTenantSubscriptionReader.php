<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Infrastructure\Directory;

use App\Modules\Tenancy\Contracts\TenantScoped;
use App\Modules\Tenancy\Contracts\TenantSubscriptionReader;
use App\Modules\Tenancy\Contracts\TenantSubscriptionStatus;
use App\Modules\Tenancy\Infrastructure\Models\TenantSubscription;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use UnexpectedValueException;

final class EloquentTenantSubscriptionReader implements TenantSubscriptionReader
{
    public function __construct(
        private readonly string $platformTimezone,
    ) {}

    public function statusForTenant(int $tenantId, DateTimeInterface $now): ?TenantSubscriptionStatus
    {
        // Subscription status is a platform/fleet read; Eloquent tenant scope is bypassed intentionally and covered by tests.
        $subscription = TenantSubscription::query()
            ->withoutGlobalScope(TenantScoped::class)
            ->where('tenant_id', $tenantId)
            ->first(['tenant_id', 'next_due_on', 'grace_days']);

        if (! $subscription instanceof TenantSubscription) {
            return null;
        }

        return $this->statusFromSubscription($subscription, $this->today($now));
    }

    public function suspendableTenantIds(DateTimeInterface $now): array
    {
        $today = $this->today($now)->format('Y-m-d');

        // Subscription suspension discovery is a platform/fleet read, not a tenant-context read.
        /** @var list<int|string> $ids */
        $ids = TenantSubscription::query()
            ->withoutGlobalScope(TenantScoped::class)
            ->whereRaw($this->suspendablePredicate(), [$today])
            ->orderBy('tenant_id')
            ->pluck('tenant_id')
            ->all();

        return array_map(fn (int|string $id): int => (int) $id, $ids);
    }

    private function statusFromSubscription(TenantSubscription $subscription, DateTimeImmutable $today): TenantSubscriptionStatus
    {
        $nextDueOn = $this->dateFromDatabase($subscription->getAttribute('next_due_on'));
        $graceDays = $this->intFromDatabase($subscription->getAttribute('grace_days'), 'grace_days');
        $graceEndsOn = $nextDueOn->modify("+{$graceDays} days");
        $isOverdue = $today > $nextDueOn;
        $isWithinGrace = $isOverdue && $today <= $graceEndsOn;

        return new TenantSubscriptionStatus(
            tenantId: $this->intFromDatabase($subscription->getAttribute('tenant_id'), 'tenant_id'),
            nextDueOn: $nextDueOn,
            graceEndsOn: $graceEndsOn,
            graceDays: $graceDays,
            isOverdue: $isOverdue,
            isWithinGrace: $isWithinGrace,
            isSuspendable: $today > $graceEndsOn,
            daysUntilDue: (int) $today->diff($nextDueOn)->format('%r%a'),
        );
    }

    private function today(DateTimeInterface $now): DateTimeImmutable
    {
        return DateTimeImmutable::createFromInterface($now)
            ->setTimezone($this->timezone())
            ->setTime(0, 0);
    }

    private function dateFromDatabase(mixed $value): DateTimeImmutable
    {
        if ($value instanceof DateTimeInterface) {
            $date = $value->format('Y-m-d');
        } elseif (is_string($value)) {
            $date = $value;
        } else {
            throw new UnexpectedValueException('Tenant subscription date value must be a database date.');
        }

        return new DateTimeImmutable($date.' 00:00:00', $this->timezone());
    }

    private function intFromDatabase(mixed $value, string $column): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        throw new UnexpectedValueException("Tenant subscription {$column} value must be an integer.");
    }

    private function timezone(): DateTimeZone
    {
        return new DateTimeZone($this->platformTimezone);
    }

    /**
     * @return literal-string
     */
    private function suspendablePredicate(): string
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            return "(next_due_on + (grace_days * interval '1 day')) < ?::date";
        }

        return "date(next_due_on, '+' || grace_days || ' days') < ?";
    }
}
