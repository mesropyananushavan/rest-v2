<?php

declare(strict_types=1);

use App\Modules\Tenancy\Application\ReactivateTenant;
use App\Modules\Tenancy\Application\RecordTenantSubscriptionPayment;
use App\Modules\Tenancy\Application\SuspendTenant;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Modules\Tenancy\Contracts\TenantSubscriptionReader;
use App\Modules\Tenancy\Domain\TenancyDomainException;
use App\Modules\Tenancy\Infrastructure\Models\Tenant;
use App\Modules\Tenancy\Infrastructure\Models\TenantSubscription;
use App\Support\Audit\AuditLog;
use App\Support\Logging\LogContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

afterEach(function (): void {
    Auth::logout();
    LogContext::clear();
    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();
});

it('advances a subscription payment exactly one period and rejects a stale double run without changing anything', function (): void {
    $tenant = subscriptionOperationsTenant('subscription-double-run');
    $subscription = subscriptionOperationsSubscription($tenant, '2026-08-01', 3);

    $updated = app(RecordTenantSubscriptionPayment::class)(
        (int) $tenant->id,
        subscriptionOperationsDate('2026-08-20'),
        subscriptionOperationsDate('2026-08-01'),
    );

    expect($updated->next_due_on?->format('Y-m-d'))->toBe('2026-09-01')
        ->and($updated->last_paid_on?->format('Y-m-d'))->toBe('2026-08-20');

    try {
        app(RecordTenantSubscriptionPayment::class)(
            (int) $tenant->id,
            subscriptionOperationsDate('2026-08-21'),
            subscriptionOperationsDate('2026-08-01'),
        );

        $this->fail('Expected stale due-date confirmation to reject the second payment run.');
    } catch (TenancyDomainException $exception) {
        expect($exception->errorCode())->toBe('tenancy.stale_due_date_confirmation');
    }

    $subscription = $subscription->refresh();

    expect($subscription->next_due_on?->format('Y-m-d'))->toBe('2026-09-01')
        ->and($subscription->last_paid_on?->format('Y-m-d'))->toBe('2026-08-20');

    app(TenantResolver::class)->set((int) $tenant->id);

    expect(AuditLog::query()->where('action', 'tenancy.subscription.payment_recorded')->count())->toBe(1);
});

it('keeps a two periods overdue tenant overdue after one payment', function (): void {
    $tenant = subscriptionOperationsTenant('subscription-still-overdue');
    subscriptionOperationsSubscription($tenant, '2026-06-01', 3);

    app(RecordTenantSubscriptionPayment::class)(
        (int) $tenant->id,
        subscriptionOperationsDate('2026-08-10'),
        subscriptionOperationsDate('2026-06-01'),
    );

    $status = app(TenantSubscriptionReader::class)
        ->statusForTenant((int) $tenant->id, subscriptionOperationsDate('2026-08-10'));

    expect($status?->nextDueOn->format('Y-m-d'))->toBe('2026-07-01')
        ->and($status?->isOverdue)->toBeTrue()
        ->and($status?->isSuspendable)->toBeTrue();
});

it('does not reactivate a tenant when recording a subscription payment', function (): void {
    $tenant = subscriptionOperationsTenant('subscription-status-unchanged', 'suspended');
    subscriptionOperationsSubscription($tenant, '2026-08-01', 3);

    app(RecordTenantSubscriptionPayment::class)(
        (int) $tenant->id,
        subscriptionOperationsDate('2026-08-02'),
        subscriptionOperationsDate('2026-08-01'),
    );

    expect($tenant->refresh()->status)->toBe('suspended');
});

it('reactivates tenants transactionally with audit and rejects active noops', function (): void {
    $tenant = subscriptionOperationsTenant('subscription-reactivate', 'suspended');

    $reactivated = app(ReactivateTenant::class)((int) $tenant->id);

    expect($reactivated->status)->toBe('active');

    app(TenantResolver::class)->set((int) $tenant->id);

    $audit = AuditLog::query()
        ->where('action', 'tenancy.tenant.reactivated')
        ->sole();

    expect((int) $audit->tenant_id)->toBe((int) $tenant->id)
        ->and($audit->actor_id)->toBeNull()
        ->and($audit->before_json['status'])->toBe('suspended')
        ->and($audit->after_json['status'])->toBe('active');

    try {
        app(ReactivateTenant::class)((int) $tenant->id);

        $this->fail('Expected active tenant reactivation to be rejected.');
    } catch (TenancyDomainException $exception) {
        expect($exception->errorCode())->toBe('tenancy.tenant_already_active');
    }

    expect($tenant->refresh()->status)->toBe('active')
        ->and(AuditLog::query()->where('action', 'tenancy.tenant.reactivated')->count())->toBe(1);
});

it('suspends tenants transactionally with audit and rejects suspended noops', function (): void {
    $tenant = subscriptionOperationsTenant('subscription-suspend');

    $suspended = app(SuspendTenant::class)((int) $tenant->id);

    expect($suspended->status)->toBe('suspended');

    app(TenantResolver::class)->set((int) $tenant->id);

    $audit = AuditLog::query()
        ->where('action', 'tenancy.tenant.suspended')
        ->sole();

    expect((int) $audit->tenant_id)->toBe((int) $tenant->id)
        ->and($audit->actor_id)->toBeNull()
        ->and($audit->before_json['status'])->toBe('active')
        ->and($audit->after_json['status'])->toBe('suspended');

    try {
        app(SuspendTenant::class)((int) $tenant->id);

        $this->fail('Expected suspended tenant suspension to be rejected.');
    } catch (TenancyDomainException $exception) {
        expect($exception->errorCode())->toBe('tenancy.tenant_already_suspended');
    }

    expect($tenant->refresh()->status)->toBe('suspended')
        ->and(AuditLog::query()->where('action', 'tenancy.tenant.suspended')->count())->toBe(1);
});

it('runs manual subscription operations end to end through console commands', function (): void {
    $tenant = subscriptionOperationsTenant('subscription-console', 'suspended');
    subscriptionOperationsSubscription($tenant, '2026-06-01', 3);

    $this->artisan('tenancy:subscription:record-payment', [
        'tenant_id' => (int) $tenant->id,
        'payment_date' => '2026-08-10',
    ])
        ->expectsOutputToContain('Tenant: #'.((int) $tenant->id).' Subscription Console')
        ->expectsOutput('Current next_due_on: 2026-06-01')
        ->expectsOutput('Resulting next_due_on: 2026-07-01')
        ->expectsConfirmation('Record this subscription payment?', 'yes')
        ->expectsOutputToContain('Subscription payment recorded')
        ->assertExitCode(0);

    $this->artisan('tenancy:tenant:reactivate', [
        'tenant_id' => (int) $tenant->id,
    ])
        ->expectsOutputToContain('WARNING: this tenant is still suspendable.')
        ->expectsConfirmation('Reactivate this tenant?', 'yes')
        ->expectsOutputToContain('Tenant reactivated')
        ->assertExitCode(0);

    $this->artisan('tenancy:tenant:suspend', [
        'tenant_id' => (int) $tenant->id,
    ])
        ->expectsConfirmation('Suspend this tenant?', 'yes')
        ->expectsOutputToContain('Tenant suspended')
        ->assertExitCode(0);

    $this->artisan('tenancy:tenant:suspend', [
        'tenant_id' => (int) $tenant->id,
    ])
        ->expectsConfirmation('Suspend this tenant?', 'yes')
        ->expectsOutput('Domain failure: tenancy.tenant_already_suspended')
        ->assertExitCode(1);

    app(TenantResolver::class)->set((int) $tenant->id);

    expect(AuditLog::query()->pluck('action')->sort()->values()->all())->toBe([
        'tenancy.subscription.payment_recorded',
        'tenancy.tenant.reactivated',
        'tenancy.tenant.suspended',
    ]);
});

it('writes audit rows for every successful manual operation with the correct tenant under PostgreSQL RLS', function (): void {
    if (! subscriptionOperationsUsesPostgresRls()) {
        $this->markTestSkipped('PostgreSQL RLS audit proof runs only on pgsql.');
    }

    $tenant = subscriptionOperationsTenant('subscription-audit-proof');
    subscriptionOperationsSubscription($tenant, '2026-08-01', 3);

    app(RecordTenantSubscriptionPayment::class)(
        (int) $tenant->id,
        subscriptionOperationsDate('2026-08-05'),
        subscriptionOperationsDate('2026-08-01'),
    );
    app(SuspendTenant::class)((int) $tenant->id);
    app(ReactivateTenant::class)((int) $tenant->id);

    app(TenantResolver::class)->clear();

    expect(DB::table('audit_logs')->pluck('tenant_id')->all())->toBe([]);

    app(TenantResolver::class)->set((int) $tenant->id);

    $audits = AuditLog::query()
        ->orderBy('id')
        ->get(['tenant_id', 'actor_id', 'action', 'target_type'])
        ->map(fn (AuditLog $audit): array => [
            'tenant_id' => (int) $audit->tenant_id,
            'actor_id' => $audit->actor_id,
            'action' => (string) $audit->action,
            'target_type' => (string) $audit->target_type,
        ])
        ->all();

    expect($audits)->toBe([
        [
            'tenant_id' => (int) $tenant->id,
            'actor_id' => null,
            'action' => 'tenancy.subscription.payment_recorded',
            'target_type' => 'tenant_subscription',
        ],
        [
            'tenant_id' => (int) $tenant->id,
            'actor_id' => null,
            'action' => 'tenancy.tenant.suspended',
            'target_type' => 'tenant',
        ],
        [
            'tenant_id' => (int) $tenant->id,
            'actor_id' => null,
            'action' => 'tenancy.tenant.reactivated',
            'target_type' => 'tenant',
        ],
    ]);
});

function subscriptionOperationsTenant(string $slug, string $status = 'active'): Tenant
{
    return Tenant::query()->create([
        'name' => str($slug)->headline()->toString(),
        'slug' => $slug,
        'default_locale' => 'hy',
        'currency' => 'AMD',
        'status' => $status,
    ]);
}

function subscriptionOperationsSubscription(Tenant $tenant, string $nextDueOn, int $graceDays): TenantSubscription
{
    return TenantSubscription::query()->create([
        'tenant_id' => (int) $tenant->id,
        'billing_anchor_day' => (int) (new DateTimeImmutable($nextDueOn))->format('j'),
        'next_due_on' => $nextDueOn,
        'grace_days' => $graceDays,
        'last_paid_on' => null,
    ]);
}

function subscriptionOperationsDate(string $date): DateTimeImmutable
{
    return new DateTimeImmutable($date.' Asia/Yerevan');
}

function subscriptionOperationsUsesPostgresRls(): bool
{
    return DB::connection()->getDriverName() === 'pgsql'
        && DB::table('pg_class')
            ->join('pg_namespace', 'pg_namespace.oid', '=', 'pg_class.relnamespace')
            ->where('pg_namespace.nspname', 'public')
            ->where('pg_class.relname', 'audit_logs')
            ->where('pg_class.relrowsecurity', true)
            ->where('pg_class.relforcerowsecurity', true)
            ->exists();
}
