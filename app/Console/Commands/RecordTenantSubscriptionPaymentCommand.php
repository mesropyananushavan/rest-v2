<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Tenancy\Application\RecordTenantSubscriptionPayment;
use App\Modules\Tenancy\Domain\MonthlyBillingCycle;
use App\Modules\Tenancy\Domain\TenancyDomainException;
use App\Modules\Tenancy\Infrastructure\Models\Tenant;
use App\Modules\Tenancy\Infrastructure\Models\TenantSubscription;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Console\Command;
use UnexpectedValueException;

final class RecordTenantSubscriptionPaymentCommand extends Command
{
    protected $signature = 'tenancy:subscription:record-payment
        {tenant_id : Tenant id}
        {payment_date : Payment date, YYYY-MM-DD}';

    protected $description = 'Record one manual tenant subscription payment and advance next_due_on by one billing period.';

    public function __construct(
        private readonly MonthlyBillingCycle $billingCycle,
    ) {
        parent::__construct();
    }

    public function handle(RecordTenantSubscriptionPayment $recordPayment): int
    {
        $tenantId = $this->tenantIdArgument();
        $paymentDate = $this->dateArgument('payment_date');

        if (! $paymentDate instanceof DateTimeImmutable) {
            return self::FAILURE;
        }

        $tenant = Tenant::query()->find($tenantId);

        if (! $tenant instanceof Tenant) {
            return $this->domainFailure(TenancyDomainException::unknownTenant());
        }

        $subscription = TenantSubscription::query()
            ->where('tenant_id', $tenantId)
            ->first();

        if (! $subscription instanceof TenantSubscription) {
            return $this->domainFailure(TenancyDomainException::subscriptionMissing());
        }

        $currentNextDueOn = $this->dateOnly($subscription->next_due_on);
        $resultingNextDueOn = $this->billingCycle->nextDueOn((int) $subscription->billing_anchor_day, $currentNextDueOn);

        $this->line(sprintf('Tenant: #%d %s (%s)', $tenantId, (string) $tenant->name, (string) $tenant->slug));
        $this->line('Current next_due_on: '.$currentNextDueOn->format('Y-m-d'));
        $this->line('Resulting next_due_on: '.$resultingNextDueOn->format('Y-m-d'));
        $this->line('Payment date: '.$paymentDate->format('Y-m-d'));

        if (! $this->confirm('Record this subscription payment?', false)) {
            $this->warn('Subscription payment cancelled.');

            return self::SUCCESS;
        }

        try {
            $updated = $recordPayment($tenantId, $paymentDate, $currentNextDueOn);
        } catch (TenancyDomainException $exception) {
            return $this->domainFailure($exception);
        }

        $this->info(sprintf(
            'Subscription payment recorded: tenant_id=%d current_next_due_on=%s resulting_next_due_on=%s.',
            $tenantId,
            $currentNextDueOn->format('Y-m-d'),
            $this->dateOnly($updated->next_due_on)->format('Y-m-d'),
        ));

        return self::SUCCESS;
    }

    private function tenantIdArgument(): int
    {
        $tenantId = filter_var($this->argument('tenant_id'), FILTER_VALIDATE_INT);

        return is_int($tenantId) ? $tenantId : 0;
    }

    private function dateArgument(string $name): ?DateTimeImmutable
    {
        $value = $this->argument($name);

        if (! is_string($value)) {
            $this->error("{$name} must be a date in YYYY-MM-DD format.");

            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, $this->timezone());

        if (! $date instanceof DateTimeImmutable || $date->format('Y-m-d') !== $value) {
            $this->error("{$name} must be a date in YYYY-MM-DD format.");

            return null;
        }

        return $date;
    }

    private function dateOnly(mixed $date): DateTimeImmutable
    {
        if (is_string($date) && $date !== '') {
            return new DateTimeImmutable($date.' 00:00:00', $this->timezone());
        }

        if (! $date instanceof DateTimeInterface) {
            throw new UnexpectedValueException('Subscription date must be a database date.');
        }

        return DateTimeImmutable::createFromInterface($date)
            ->setTimezone($this->timezone())
            ->setTime(0, 0);
    }

    private function timezone(): DateTimeZone
    {
        $timezone = config('billing.platform_timezone');
        assert(is_string($timezone));

        return new DateTimeZone($timezone);
    }

    private function domainFailure(TenancyDomainException $exception): int
    {
        $this->error('Domain failure: '.$exception->errorCode());

        return self::FAILURE;
    }
}
