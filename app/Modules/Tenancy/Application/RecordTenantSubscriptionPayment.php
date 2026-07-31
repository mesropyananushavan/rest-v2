<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Application;

use App\Modules\Tenancy\Domain\MonthlyBillingCycle;
use App\Modules\Tenancy\Domain\TenancyDomainException;
use App\Modules\Tenancy\Infrastructure\Models\Tenant;
use App\Modules\Tenancy\Infrastructure\Models\TenantSubscription;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use UnexpectedValueException;

final class RecordTenantSubscriptionPayment
{
    use RecordsTenantLifecycleAction;

    public function __construct(
        private readonly MonthlyBillingCycle $billingCycle,
    ) {}

    public function __invoke(int $tenantId, DateTimeInterface $paymentDate, DateTimeInterface $expectedCurrentNextDueOn): TenantSubscription
    {
        $startedAt = microtime(true);

        try {
            $subscription = $this->withTenantAuditContext(
                $tenantId,
                fn (): TenantSubscription => DB::transaction(function () use ($expectedCurrentNextDueOn, $paymentDate, $tenantId): TenantSubscription {
                    $tenant = Tenant::query()
                        ->whereKey($tenantId)
                        ->lockForUpdate()
                        ->first();

                    if (! $tenant instanceof Tenant) {
                        throw TenancyDomainException::unknownTenant();
                    }

                    $subscription = TenantSubscription::query()
                        ->where('tenant_id', $tenantId)
                        ->lockForUpdate()
                        ->first();

                    if (! $subscription instanceof TenantSubscription) {
                        throw TenancyDomainException::subscriptionMissing();
                    }

                    $storedNextDueOn = $this->dateOnly($subscription->next_due_on);

                    if ($storedNextDueOn->format('Y-m-d') !== $this->dateOnly($expectedCurrentNextDueOn)->format('Y-m-d')) {
                        throw TenancyDomainException::staleDueDateConfirmation();
                    }

                    $before = $this->subscriptionAuditPayload($subscription);
                    $nextDueOn = $this->billingCycle->nextDueOn((int) $subscription->billing_anchor_day, $storedNextDueOn);

                    $subscription->forceFill([
                        'last_paid_on' => $this->dateOnly($paymentDate)->format('Y-m-d'),
                        'next_due_on' => $nextDueOn->format('Y-m-d'),
                    ])->save();

                    $subscription = $subscription->refresh();

                    $this->auditTenantMutation(
                        'tenancy.subscription.payment_recorded',
                        'tenant_subscription',
                        (int) $subscription->id,
                        $before,
                        $this->subscriptionAuditPayload($subscription),
                    );

                    return $subscription;
                }),
            );

            $this->logSuccess('tenancy.subscription.record_payment', $startedAt, [
                'tenant_id' => $tenantId,
                'subscription_id' => (int) $subscription->id,
            ]);

            return $subscription;
        } catch (TenancyDomainException $exception) {
            $this->logDomainFailure('tenancy.subscription.record_payment', $exception, $startedAt, [
                'tenant_id' => $tenantId,
            ]);

            throw $exception;
        }
    }

    private function dateOnly(mixed $date): DateTimeImmutable
    {
        if (is_string($date) && $date !== '') {
            return new DateTimeImmutable($date.' 00:00:00');
        }

        if (! $date instanceof DateTimeInterface) {
            throw new UnexpectedValueException('Subscription date must be a database date.');
        }

        return DateTimeImmutable::createFromInterface($date)->setTime(0, 0);
    }
}
