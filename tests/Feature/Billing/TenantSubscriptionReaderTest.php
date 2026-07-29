<?php

declare(strict_types=1);

use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Modules\Tenancy\Contracts\TenantSubscriptionReader;
use App\Modules\Tenancy\Infrastructure\Models\Tenant;
use App\Modules\Tenancy\Infrastructure\Models\TenantSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

afterEach(function (): void {
    app(TenantResolver::class)->clear();
});

it('reports grace boundaries with inclusive serviceability semantics', function (): void {
    $tenant = subscriptionReaderTenant('tenant-a');
    subscriptionReaderCreateSubscription($tenant, '2026-08-01', 3);

    $reader = app(TenantSubscriptionReader::class);

    $dueDay = $reader->statusForTenant((int) $tenant->id, subscriptionReaderNow('2026-08-01 10:00:00'));
    $insideGrace = $reader->statusForTenant((int) $tenant->id, subscriptionReaderNow('2026-08-04 23:59:59'));
    $firstSuspendableDay = $reader->statusForTenant((int) $tenant->id, subscriptionReaderNow('2026-08-05 00:00:00'));

    expect($dueDay)->not->toBeNull()
        ->and($dueDay?->nextDueOn->format('Y-m-d'))->toBe('2026-08-01')
        ->and($dueDay?->graceEndsOn->format('Y-m-d'))->toBe('2026-08-04')
        ->and($dueDay?->daysUntilDue)->toBe(0)
        ->and($dueDay?->isOverdue)->toBeFalse()
        ->and($dueDay?->isWithinGrace)->toBeFalse()
        ->and($dueDay?->isSuspendable)->toBeFalse()
        ->and($insideGrace?->daysUntilDue)->toBe(-3)
        ->and($insideGrace?->isOverdue)->toBeTrue()
        ->and($insideGrace?->isWithinGrace)->toBeTrue()
        ->and($insideGrace?->isSuspendable)->toBeFalse()
        ->and($firstSuspendableDay?->daysUntilDue)->toBe(-4)
        ->and($firstSuspendableDay?->isOverdue)->toBeTrue()
        ->and($firstSuspendableDay?->isWithinGrace)->toBeFalse()
        ->and($firstSuspendableDay?->isSuspendable)->toBeTrue();
});

it('evaluates today in the configured platform billing timezone', function (): void {
    config(['billing.platform_timezone' => 'Asia/Yerevan']);

    $tenant = subscriptionReaderTenant('tenant-a');
    subscriptionReaderCreateSubscription($tenant, '2026-08-01', 3);

    $status = app(TenantSubscriptionReader::class)
        ->statusForTenant((int) $tenant->id, new DateTimeImmutable('2026-08-04 20:30:00 UTC'));

    expect($status?->isSuspendable)->toBeTrue()
        ->and($status?->daysUntilDue)->toBe(-4);
});

it('treats tenants without subscription rows as not suspendable', function (): void {
    $tenantWithoutSubscription = subscriptionReaderTenant('tenant-a');
    $tenantWithSubscription = subscriptionReaderTenant('tenant-b');
    subscriptionReaderCreateSubscription($tenantWithSubscription, '2026-08-01', 3);

    $reader = app(TenantSubscriptionReader::class);

    expect($reader->statusForTenant((int) $tenantWithoutSubscription->id, subscriptionReaderNow('2026-08-05 10:00:00')))
        ->toBeNull()
        ->and($reader->suspendableTenantIds(subscriptionReaderNow('2026-08-05 10:00:00')))
        ->toBe([(int) $tenantWithSubscription->id]);
});

it('lists suspendable tenants in one query without requiring a tenant context', function (): void {
    $suspendable = subscriptionReaderTenant('tenant-a');
    $withinGrace = subscriptionReaderTenant('tenant-b');
    $missingSubscription = subscriptionReaderTenant('tenant-c');

    subscriptionReaderCreateSubscription($suspendable, '2026-08-01', 3);
    subscriptionReaderCreateSubscription($withinGrace, '2026-08-02', 3);

    app(TenantResolver::class)->clear();

    DB::enableQueryLog();

    $ids = app(TenantSubscriptionReader::class)
        ->suspendableTenantIds(subscriptionReaderNow('2026-08-05 10:00:00'));

    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($ids)->toBe([(int) $suspendable->id])
        ->and($ids)->not->toContain((int) $withinGrace->id)
        ->and($ids)->not->toContain((int) $missingSubscription->id)
        ->and($queryCount)->toBe(1);
});

function subscriptionReaderTenant(string $slug): Tenant
{
    return Tenant::query()->create([
        'name' => str($slug)->headline()->toString(),
        'slug' => $slug,
        'default_locale' => 'hy',
        'currency' => 'AMD',
        'status' => 'active',
    ]);
}

function subscriptionReaderCreateSubscription(Tenant $tenant, string $nextDueOn, int $graceDays): TenantSubscription
{
    app(TenantResolver::class)->set((int) $tenant->id);

    $subscription = TenantSubscription::query()->create([
        'billing_anchor_day' => (int) (new DateTimeImmutable($nextDueOn))->format('j'),
        'next_due_on' => $nextDueOn,
        'grace_days' => $graceDays,
        'last_paid_on' => null,
    ]);

    app(TenantResolver::class)->clear();

    return $subscription;
}

function subscriptionReaderNow(string $dateTime): DateTimeImmutable
{
    return new DateTimeImmutable($dateTime.' Asia/Yerevan');
}
