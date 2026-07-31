<?php

declare(strict_types=1);

use App\Modules\Tenancy\Application\SuspendOverdueTenantSubscriptions;
use App\Modules\Tenancy\Application\SuspendTenant;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantDirectory;
use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Modules\Tenancy\Contracts\TenantSubscriptionReader;
use App\Modules\Tenancy\Contracts\TenantSubscriptionStatus;
use App\Modules\Tenancy\Infrastructure\Models\Branch;
use App\Modules\Tenancy\Infrastructure\Models\Tenant;
use App\Modules\Tenancy\Infrastructure\Models\TenantSubscription;
use App\Support\Audit\AuditLog;
use App\Support\Logging\LogContext;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

afterEach(function (): void {
    LogContext::clear();
    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();
});

it('does not suspend tenants before the configured quiet hour', function (): void {
    $tenant = autoSuspensionTenant('auto-before-quiet');
    autoSuspensionSubscription($tenant, '2026-08-01', 3);

    $result = app(SuspendOverdueTenantSubscriptions::class)(autoSuspensionNow('2026-08-05 04:59:00'));

    expect($result->quietHourReached)->toBeFalse()
        ->and($result->candidateCount)->toBe(0)
        ->and($result->suspendedCount)->toBe(0)
        ->and($tenant->refresh()->status)->toBe('active');

    autoSuspensionInTenant($tenant);
    expect(AuditLog::query()->where('action', 'tenancy.tenant.suspended')->count())->toBe(0);
});

it('suspends eligible active tenants at the quiet hour without mutating missing non eligible or already suspended tenants', function (): void {
    $eligible = autoSuspensionTenant('auto-eligible');
    $notEligible = autoSuspensionTenant('auto-not-eligible');
    $missingSubscription = autoSuspensionTenant('auto-missing-subscription');
    $alreadySuspended = autoSuspensionTenant('auto-already-suspended', 'suspended');

    autoSuspensionSubscription($eligible, '2026-08-01', 3);
    autoSuspensionSubscription($notEligible, '2026-08-04', 3);
    autoSuspensionSubscription($alreadySuspended, '2026-08-01', 3);

    $result = app(SuspendOverdueTenantSubscriptions::class)(autoSuspensionNow('2026-08-05 05:00:00'));

    expect($result->quietHourReached)->toBeTrue()
        ->and($result->candidateCount)->toBe(2)
        ->and($result->suspendedCount)->toBe(1)
        ->and($result->skippedNotServiceableCount)->toBe(1)
        ->and($eligible->refresh()->status)->toBe('suspended')
        ->and($notEligible->refresh()->status)->toBe('active')
        ->and($missingSubscription->refresh()->status)->toBe('active')
        ->and($alreadySuspended->refresh()->status)->toBe('suspended');

    autoSuspensionInTenant($eligible);
    $audit = AuditLog::query()
        ->where('action', 'tenancy.tenant.suspended')
        ->sole();

    expect((int) $audit->tenant_id)->toBe((int) $eligible->id)
        ->and($audit->branch_id)->toBeNull()
        ->and($audit->actor_id)->toBeNull()
        ->and($audit->before_json['status'])->toBe('active')
        ->and($audit->after_json['status'])->toBe('suspended');
});

it('is idempotent on repeated runs and does not duplicate suspension audit events', function (): void {
    $tenant = autoSuspensionTenant('auto-repeat');
    autoSuspensionSubscription($tenant, '2026-08-01', 3);

    $first = app(SuspendOverdueTenantSubscriptions::class)(autoSuspensionNow('2026-08-05 06:00:00'));
    $second = app(SuspendOverdueTenantSubscriptions::class)(autoSuspensionNow('2026-08-05 07:00:00'));

    expect($first->suspendedCount)->toBe(1)
        ->and($second->candidateCount)->toBe(1)
        ->and($second->suspendedCount)->toBe(0)
        ->and($second->skippedNotServiceableCount)->toBe(1)
        ->and($tenant->refresh()->status)->toBe('suspended');

    autoSuspensionInTenant($tenant);
    expect(AuditLog::query()->where('action', 'tenancy.tenant.suspended')->count())->toBe(1);
});

it('rechecks stale fleet candidates and skips tenants that are no longer suspendable', function (): void {
    $tenant = autoSuspensionTenant('auto-stale-candidate');
    autoSuspensionSubscription($tenant, '2026-08-01', 3);
    $reader = autoSuspensionReader(
        ids: [(int) $tenant->id],
        statuses: [
            (int) $tenant->id => autoSuspensionStatus((int) $tenant->id, suspendable: false),
        ],
    );

    $result = autoSuspensionAction($reader)(autoSuspensionNow('2026-08-05 08:00:00'));

    expect($result->candidateCount)->toBe(1)
        ->and($result->suspendedCount)->toBe(0)
        ->and($result->skippedNoLongerSuspendableCount)->toBe(1)
        ->and($tenant->refresh()->status)->toBe('active');
});

it('continues after expected tenant-level noops and still suspends other eligible tenants', function (): void {
    $alreadySuspended = autoSuspensionTenant('auto-race-suspended', 'suspended');
    $deletedTenantId = 999999;
    $eligible = autoSuspensionTenant('auto-race-eligible');
    autoSuspensionSubscription($alreadySuspended, '2026-08-01', 3);
    autoSuspensionSubscription($eligible, '2026-08-01', 3);
    $reader = autoSuspensionReader(
        ids: [(int) $alreadySuspended->id, $deletedTenantId, (int) $eligible->id],
        statuses: [
            (int) $alreadySuspended->id => autoSuspensionStatus((int) $alreadySuspended->id, suspendable: true),
            $deletedTenantId => autoSuspensionStatus($deletedTenantId, suspendable: true),
            (int) $eligible->id => autoSuspensionStatus((int) $eligible->id, suspendable: true),
        ],
    );
    $tenantDirectory = autoSuspensionTenantDirectory(serviceableTenantIds: [
        (int) $alreadySuspended->id,
        $deletedTenantId,
        (int) $eligible->id,
    ]);

    $result = autoSuspensionAction($reader, $tenantDirectory)(autoSuspensionNow('2026-08-05 08:00:00'));

    expect($result->candidateCount)->toBe(3)
        ->and($result->suspendedCount)->toBe(1)
        ->and($result->skippedAlreadySuspendedCount)->toBe(1)
        ->and($result->skippedUnknownTenantCount)->toBe(1)
        ->and($eligible->refresh()->status)->toBe('suspended')
        ->and($alreadySuspended->refresh()->status)->toBe('suspended');
});

it('surfaces unexpected failures and restores tenant branch and log context', function (): void {
    config(['billing.automatic_suspension.quiet_hour' => 'invalid']);
    $previousTenant = autoSuspensionTenant('auto-previous-context');
    autoSuspensionInTenant($previousTenant);
    $branch = Branch::query()->create([
        'name' => 'Previous Branch',
        'timezone' => 'Asia/Yerevan',
        'status' => 'active',
    ]);
    app(BranchContext::class)->set((int) $branch->id);
    LogContext::start('auto-previous-request', 'orders');

    expect(fn () => app(SuspendOverdueTenantSubscriptions::class)(autoSuspensionNow('2026-08-05 08:00:00')))
        ->toThrow(UnexpectedValueException::class);

    $context = LogContext::current();

    expect(app(TenantResolver::class)->id())->toBe((int) $previousTenant->id)
        ->and(app(BranchContext::class)->id())->toBe((int) $branch->id)
        ->and($context['request_id'])->toBe('auto-previous-request')
        ->and($context['tenant_id'])->toBe((int) $previousTenant->id)
        ->and($context['branch_id'])->toBe((int) $branch->id)
        ->and($context['module'])->toBe('orders');
});

it('uses the fleet reader without per-tenant queries when there are no candidates', function (): void {
    $tenant = autoSuspensionTenant('auto-query-count');
    autoSuspensionSubscription($tenant, '2026-08-04', 3);

    DB::enableQueryLog();

    $result = app(SuspendOverdueTenantSubscriptions::class)(autoSuspensionNow('2026-08-05 08:00:00'));

    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();
    $expectedQueryCount = DB::connection()->getDriverName() === 'pgsql' ? 5 : 1;

    expect($result->candidateCount)->toBe(0)
        ->and($result->suspendedCount)->toBe(0)
        ->and($queryCount)->toBe($expectedQueryCount);
});

it('registers the automatic suspension schedule hourly in the platform timezone without overlap', function (): void {
    config(['billing.platform_timezone' => 'Asia/Yerevan']);
    $this->artisan('schedule:list')->assertSuccessful();

    $event = collect(app(Schedule::class)->events())
        ->first(fn (mixed $event): bool => str_contains((string) data_get($event, 'command'), 'tenancy:subscriptions:auto-suspend'));

    expect($event)->not->toBeNull()
        ->and($event->command)->toContain('tenancy:subscriptions:auto-suspend')
        ->and($event->expression)->toBe('0 * * * *')
        ->and($event->timezone)->toBe('Asia/Yerevan')
        ->and($event->withoutOverlapping)->toBeTrue()
        ->and($event->expiresAt)->toBe(1440);
});

it('writes suspension audit rows visible only in the target tenant context under PostgreSQL RLS', function (): void {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('PostgreSQL RLS audit proof runs only on pgsql.');
    }

    $tenant = autoSuspensionTenant('auto-pgsql-audit');
    autoSuspensionSubscription($tenant, '2026-08-01', 3);

    app(SuspendOverdueTenantSubscriptions::class)(autoSuspensionNow('2026-08-05 08:00:00'));

    app(TenantResolver::class)->clear();

    expect(DB::table('audit_logs')->pluck('id')->all())->toBe([]);

    autoSuspensionInTenant($tenant);

    $audit = AuditLog::query()
        ->where('action', 'tenancy.tenant.suspended')
        ->sole();

    expect((int) $audit->tenant_id)->toBe((int) $tenant->id)
        ->and($audit->branch_id)->toBeNull()
        ->and($audit->actor_id)->toBeNull();
});

function autoSuspensionTenant(string $slug, string $status = 'active'): Tenant
{
    return Tenant::query()->create([
        'name' => str($slug)->headline()->toString(),
        'slug' => $slug,
        'default_locale' => 'hy',
        'currency' => 'AMD',
        'status' => $status,
    ]);
}

function autoSuspensionSubscription(Tenant $tenant, string $nextDueOn, int $graceDays): TenantSubscription
{
    return TenantSubscription::query()->create([
        'tenant_id' => (int) $tenant->id,
        'billing_anchor_day' => (int) (new DateTimeImmutable($nextDueOn))->format('j'),
        'next_due_on' => $nextDueOn,
        'grace_days' => $graceDays,
        'last_paid_on' => null,
    ]);
}

function autoSuspensionNow(string $dateTime): DateTimeImmutable
{
    return new DateTimeImmutable($dateTime.' Asia/Yerevan');
}

function autoSuspensionInTenant(Tenant $tenant): void
{
    app(TenantResolver::class)->set((int) $tenant->id);
    app(BranchContext::class)->clear();
}

function autoSuspensionStatus(int $tenantId, bool $suspendable): TenantSubscriptionStatus
{
    return new TenantSubscriptionStatus(
        tenantId: $tenantId,
        nextDueOn: new DateTimeImmutable('2026-08-01 00:00:00 Asia/Yerevan'),
        graceEndsOn: new DateTimeImmutable($suspendable ? '2026-08-04 00:00:00 Asia/Yerevan' : '2026-08-10 00:00:00 Asia/Yerevan'),
        graceDays: 3,
        isOverdue: true,
        isWithinGrace: ! $suspendable,
        isSuspendable: $suspendable,
        daysUntilDue: -4,
    );
}

/**
 * @param  list<int>  $ids
 * @param  array<int, TenantSubscriptionStatus|null>  $statuses
 */
function autoSuspensionReader(array $ids, array $statuses): TenantSubscriptionReader
{
    return new class($ids, $statuses) implements TenantSubscriptionReader
    {
        /**
         * @param  list<int>  $ids
         * @param  array<int, TenantSubscriptionStatus|null>  $statuses
         */
        public function __construct(
            private readonly array $ids,
            private readonly array $statuses,
        ) {}

        public function statusForTenant(int $tenantId, DateTimeInterface $now): ?TenantSubscriptionStatus
        {
            return $this->statuses[$tenantId] ?? null;
        }

        public function suspendableTenantIds(DateTimeInterface $now): array
        {
            return $this->ids;
        }
    };
}

/**
 * @param  list<int>  $serviceableTenantIds
 */
function autoSuspensionTenantDirectory(array $serviceableTenantIds): TenantDirectory
{
    return new class($serviceableTenantIds) implements TenantDirectory
    {
        /**
         * @param  list<int>  $serviceableTenantIds
         */
        public function __construct(
            private readonly array $serviceableTenantIds,
        ) {}

        public function activeTenantIds(): array
        {
            return $this->serviceableTenantIds;
        }

        public function isServiceable(int $tenantId): bool
        {
            return in_array($tenantId, $this->serviceableTenantIds, true);
        }

        public function tenantName(int $tenantId): ?string
        {
            return null;
        }

        public function branchSummariesForIds(array $branchIds): array
        {
            return [];
        }
    };
}

function autoSuspensionAction(
    TenantSubscriptionReader $reader,
    ?TenantDirectory $tenantDirectory = null,
): SuspendOverdueTenantSubscriptions {
    return new SuspendOverdueTenantSubscriptions(
        subscriptions: $reader,
        tenants: $tenantDirectory ?? app(TenantDirectory::class),
        suspendTenant: app(SuspendTenant::class),
        tenantResolver: app(TenantResolver::class),
        branches: app(BranchContext::class),
    );
}
