<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Models\Permission;
use App\Modules\Identity\Infrastructure\Models\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Identity\Infrastructure\Models\UserBranchAssignment;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Modules\Tenancy\Infrastructure\Models\Branch;
use App\Modules\Tenancy\Infrastructure\Models\Tenant;
use App\Support\Audit\Application\AuditLogReportFilters;
use App\Support\Audit\Application\AuditLogReportRow;
use App\Support\Audit\Application\BrowseAuditLogs;
use App\Support\Audit\AuditLogPermissions;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

afterEach(function (): void {
    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();
    app()->setLocale('en');
});

it('seeds audit log viewing for owners only', function (): void {
    $this->seed(DemoSeeder::class);

    $usersByEmail = User::withoutGlobalScopes()
        ->whereIn('email', [
            'owner@arat.test',
            'manager@arat.test',
            'cashier@arat.test',
            'waiter@arat.test',
        ])
        ->get()
        ->keyBy('email');

    $owner = $usersByEmail->get('owner@arat.test');

    expect($owner)->toBeInstanceOf(User::class);
    app(TenantResolver::class)->set((int) $owner->tenant_id);

    $permissionsByEmail = $usersByEmail->map(
        fn (User $user): array => $user->role()->firstOrFail()->permissions()->pluck('code')->all(),
    );

    expect($permissionsByEmail->get('owner@arat.test'))->toContain(AuditLogPermissions::VIEW)
        ->and($permissionsByEmail->get('manager@arat.test'))->not->toContain(AuditLogPermissions::VIEW)
        ->and($permissionsByEmail->get('cashier@arat.test'))->not->toContain(AuditLogPermissions::VIEW)
        ->and($permissionsByEmail->get('waiter@arat.test'))->not->toContain(AuditLogPermissions::VIEW);
});

it('lists paginated audit rows with combinable filters and fixed branch scope', function (): void {
    $record = auditReportUser('audit-report-list', 'audit-owner', [AuditLogPermissions::VIEW], branchCount: 2);
    $now = CarbonImmutable::now($record['branches'][0]->timezone)->startOfDay()->addHours(10);

    $matching = auditReportInsertLog($record, branchIndex: 0, actor: $record['user'], action: 'menu.category.archived', targetType: 'menu_category', createdAt: $now);
    auditReportInsertLog($record, branchIndex: 0, actor: $record['user'], action: 'menu.item.archived', targetType: 'menu_item', createdAt: $now);
    auditReportInsertLog($record, branchIndex: 1, actor: $record['user'], action: 'menu.category.archived', targetType: 'menu_category', createdAt: $now);

    $response = $this->actingAs($record['user'])
        ->withSession(['branch_id' => (int) $record['branches'][0]->id])
        ->get(route('admin.audit-logs.index', [
            'date_from' => $now->subDays(1)->format('Y-m-d'),
            'date_to' => $now->addDay()->format('Y-m-d'),
            'actor_id' => (int) $record['user']->id,
            'action' => 'menu.category.archived',
            'target_type' => 'menu_category',
            'branch_id' => (int) $record['branches'][0]->id,
        ]))
        ->assertOk()
        ->assertSee(__('admin.audit_logs.heading'), false)
        ->assertSee(__('admin.nav.audit_logs'), false)
        ->assertSee('menu.category.archived', false)
        ->assertSee('menu_category', false)
        ->assertSee($record['user']->name.' #'.$record['user']->id, false)
        ->assertSee("/admin/audit-logs/{$matching}", false)
        ->assertDontSee('menu.item.archived', false);

    assertRenderedHtmlHasNoUncompiledBladeDirectiveAttributes($response->getContent());
    expect($response->getContent())->not->toContain('wire:');
});

it('denies users without audit permission and hides the navigation entry', function (): void {
    $record = auditReportUser('audit-report-denied', 'audit-manager', ['menu.items.manage']);

    $this->actingAs($record['user'])
        ->withSession(['branch_id' => (int) $record['branches'][0]->id])
        ->get(route('admin.audit-logs.index'))
        ->assertForbidden();

    $this->actingAs($record['user'])
        ->withSession(['branch_id' => (int) $record['branches'][0]->id])
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertDontSee(__('admin.nav.audit_logs'), false);
});

it('proves tenant and branch isolation for audit report rows', function (): void {
    $tenantA = auditReportUser('audit-report-tenant-a', 'audit-owner-a', [AuditLogPermissions::VIEW], branchCount: 2, assignAllBranches: false);
    $tenantB = auditReportUser('audit-report-tenant-b', 'audit-owner-b', [AuditLogPermissions::VIEW]);
    $now = CarbonImmutable::now('Asia/Yerevan')->startOfDay()->addHours(11);

    auditReportInsertLog($tenantA, branchIndex: 0, actor: $tenantA['user'], action: 'visible.action', targetType: 'visible_target', createdAt: $now);
    auditReportInsertLog($tenantA, branchIndex: 1, actor: $tenantA['user'], action: 'hidden.branch', targetType: 'hidden_branch_target', createdAt: $now);
    auditReportInsertLog($tenantB, branchIndex: 0, actor: $tenantB['user'], action: 'hidden.tenant', targetType: 'hidden_tenant_target', createdAt: $now);

    $this->actingAs($tenantA['user'])
        ->withSession(['branch_id' => (int) $tenantA['branches'][0]->id])
        ->get(route('admin.audit-logs.index', [
            'date_from' => $now->subDay()->format('Y-m-d'),
            'date_to' => $now->addDay()->format('Y-m-d'),
        ]))
        ->assertOk()
        ->assertSee('visible.action', false)
        ->assertDontSee('hidden.branch', false)
        ->assertDontSee('hidden.tenant', false);

    $this->actingAs($tenantA['user'])
        ->withSession(['branch_id' => (int) $tenantA['branches'][0]->id])
        ->get(route('admin.audit-logs.index', [
            'date_from' => $now->subDay()->format('Y-m-d'),
            'date_to' => $now->addDay()->format('Y-m-d'),
            'branch_id' => (int) $tenantA['branches'][1]->id,
        ]))
        ->assertRedirect()
        ->assertSessionHasErrors('branch_id');
});

it('enforces the maximum audit window server side', function (): void {
    $record = auditReportUser('audit-report-window', 'audit-owner', [AuditLogPermissions::VIEW]);

    $this->actingAs($record['user'])
        ->withSession(['branch_id' => (int) $record['branches'][0]->id])
        ->from(route('admin.audit-logs.index'))
        ->get(route('admin.audit-logs.index', [
            'date_from' => '2026-01-01',
            'date_to' => '2026-02-01',
        ]))
        ->assertRedirect(route('admin.audit-logs.index'))
        ->assertSessionHasErrors([
            'date_to' => __('admin.audit_logs.errors.window_too_large', [
                'days' => BrowseAuditLogs::MAX_WINDOW_DAYS,
            ]),
        ]);
});

it('renders audit JSON details as escaped text', function (): void {
    $record = auditReportUser('audit-report-detail', 'audit-owner', [AuditLogPermissions::VIEW]);
    $now = CarbonImmutable::now('Asia/Yerevan')->startOfDay()->addHours(12);
    $auditLogId = auditReportInsertLog(
        $record,
        branchIndex: 0,
        actor: $record['user'],
        action: 'menu.category.updated',
        targetType: 'menu_category',
        createdAt: $now,
        before: ['name' => '<script>alert("before")</script>'],
        after: ['name' => '<strong>After</strong>'],
    );

    $response = $this->actingAs($record['user'])
        ->withSession(['branch_id' => (int) $record['branches'][0]->id])
        ->get(route('admin.audit-logs.show', [
            'auditLog' => $auditLogId,
            'date_from' => $now->subDay()->format('Y-m-d'),
            'date_to' => $now->addDay()->format('Y-m-d'),
        ]))
        ->assertOk()
        ->assertSee(__('admin.audit_logs.detail.heading', ['id' => $auditLogId]), false)
        ->assertSee('&lt;script&gt;alert(', false)
        ->assertSee('before', false)
        ->assertSee(e('<strong>After</strong>'), false)
        ->assertSee('audit-report-detail-request', false)
        ->assertDontSee('<script>alert("before")</script>', false)
        ->assertDontSee('<strong>After</strong>', false);

    assertRenderedHtmlHasNoUncompiledBladeDirectiveAttributes($response->getContent());
    expect($response->getContent())->not->toContain('wire:');
});

it('keeps audit list query count fixed when actor names grow from one to twenty five rows', function (): void {
    $record = auditReportUser('audit-report-query-count', 'audit-owner', [AuditLogPermissions::VIEW]);
    $now = CarbonImmutable::now('Asia/Yerevan')->startOfDay()->addHours(13);

    auditReportInsertLog($record, branchIndex: 0, actor: $record['user'], action: 'query.one', targetType: 'query_target', createdAt: $now);

    $oneRowCount = auditReportQueryCount(fn (): int => auditReportPaginate($record, $now)->count());

    foreach (range(2, 25) as $index) {
        $actor = auditReportAdditionalUser($record, "actor-{$index}");
        auditReportInsertLog($record, branchIndex: 0, actor: $actor, action: 'query.many', targetType: 'query_target', createdAt: $now->addMinutes($index));
    }

    $twentyFiveRowCount = auditReportQueryCount(fn (): int => auditReportPaginate($record, $now)->count());

    expect($oneRowCount)->toBe(2)
        ->and($twentyFiveRowCount)->toBe(2);
});

it('keeps admin translation key sets identical across locales after adding audit log strings', function (): void {
    $keySets = collect(['hy', 'ru', 'en'])
        ->mapWithKeys(fn (string $locale): array => [$locale => auditReportFlattenKeys(require lang_path("{$locale}/admin.php"))]);

    expect($keySets['hy'])->toBe($keySets['en'])
        ->and($keySets['ru'])->toBe($keySets['en']);
});

/**
 * @param  list<string>  $permissionCodes
 * @return array{tenant: Tenant, branches: list<Branch>, user: User}
 */
function auditReportUser(
    string $tenantSlug,
    string $username,
    array $permissionCodes,
    int $branchCount = 1,
    bool $assignAllBranches = true,
): array {
    $tenant = Tenant::query()->create([
        'name' => str($tenantSlug)->headline()->toString(),
        'slug' => $tenantSlug,
        'default_locale' => 'en',
        'currency' => 'AMD',
        'status' => 'active',
    ]);

    app(TenantResolver::class)->set((int) $tenant->id);

    $branches = [];
    for ($index = 1; $index <= $branchCount; $index++) {
        $branches[] = Branch::query()->create([
            'name' => "{$tenantSlug} Branch {$index}",
            'timezone' => 'Asia/Yerevan',
            'status' => 'active',
        ]);
    }

    app(BranchContext::class)->set((int) $branches[0]->id);

    $role = Role::query()->create([
        'code' => "{$username}-role",
        'name' => "{$username} Role",
    ]);

    $permissions = collect($permissionCodes)
        ->map(fn (string $code): Permission => Permission::query()->create([
            'code' => $code,
            'name' => $code,
        ]));

    $role->permissions()->attach(
        $permissions->pluck('id')->all(),
        ['tenant_id' => (int) $tenant->id],
    );

    $user = User::query()->create([
        'role_id' => (int) $role->id,
        'name' => $username,
        'email' => "{$username}@smartrest.test",
        'username' => $username,
        'default_locale' => 'en',
        'active' => true,
        'is_superadmin' => false,
        'password' => Hash::make('password'),
    ]);

    foreach ($branches as $index => $branch) {
        if (! $assignAllBranches && $index > 0) {
            continue;
        }

        UserBranchAssignment::query()->create([
            'user_id' => (int) $user->id,
            'branch_id' => (int) $branch->id,
        ]);
    }

    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();

    return [
        'tenant' => $tenant,
        'branches' => $branches,
        'user' => $user,
    ];
}

/**
 * @param  array{tenant: Tenant, branches: list<Branch>, user: User}  $record
 */
function auditReportAdditionalUser(array $record, string $username): User
{
    app(TenantResolver::class)->set((int) $record['tenant']->id);
    app(BranchContext::class)->set((int) $record['branches'][0]->id);

    $user = User::query()->create([
        'name' => $username,
        'email' => "{$username}@smartrest.test",
        'username' => $username,
        'default_locale' => 'en',
        'active' => true,
        'is_superadmin' => false,
        'password' => Hash::make('password'),
    ]);

    UserBranchAssignment::query()->create([
        'user_id' => (int) $user->id,
        'branch_id' => (int) $record['branches'][0]->id,
    ]);

    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();

    return $user;
}

/**
 * @param  array{tenant: Tenant, branches: list<Branch>, user: User}  $record
 * @param  array<string, mixed>|null  $before
 * @param  array<string, mixed>|null  $after
 */
function auditReportInsertLog(
    array $record,
    int $branchIndex,
    User $actor,
    string $action,
    string $targetType,
    CarbonImmutable $createdAt,
    ?array $before = null,
    ?array $after = null,
): int {
    $createdAtUtc = $createdAt->setTimezone('UTC');

    return (int) DB::table('audit_logs')->insertGetId([
        'tenant_id' => (int) $record['tenant']->id,
        'branch_id' => (int) $record['branches'][$branchIndex]->id,
        'actor_id' => (int) $actor->id,
        'action' => $action,
        'target_type' => $targetType,
        'target_id' => $branchIndex + 100,
        'before_json' => $before === null ? null : json_encode($before, JSON_THROW_ON_ERROR),
        'after_json' => $after === null ? null : json_encode($after, JSON_THROW_ON_ERROR),
        'correlation_id' => "{$record['tenant']->slug}-request",
        'ip_address' => '127.0.0.1',
        'created_at' => $createdAtUtc,
        'updated_at' => $createdAtUtc,
    ]);
}

/**
 * @param  array{tenant: Tenant, branches: list<Branch>, user: User}  $record
 * @return LengthAwarePaginator<int, AuditLogReportRow>
 */
function auditReportPaginate(array $record, CarbonImmutable $now): Illuminate\Pagination\LengthAwarePaginator
{
    app(TenantResolver::class)->set((int) $record['tenant']->id);

    return app(BrowseAuditLogs::class)->paginate(new AuditLogReportFilters(
        dateFrom: $now->subDay()->format('Y-m-d'),
        dateTo: $now->addDay()->format('Y-m-d'),
        fromUtc: $now->subDay()->startOfDay()->setTimezone('UTC'),
        toUtc: $now->addDay()->endOfDay()->setTimezone('UTC'),
        visibleBranchIds: [(int) $record['branches'][0]->id],
    ), 'Asia/Yerevan');
}

function auditReportQueryCount(Closure $callback): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    $callback();

    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

/**
 * @return list<string>
 */
function auditReportFlattenKeys(array $values, string $prefix = ''): array
{
    $keys = [];

    foreach ($values as $key => $value) {
        $fullKey = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

        if (is_array($value)) {
            array_push($keys, ...auditReportFlattenKeys($value, $fullKey));

            continue;
        }

        $keys[] = $fullKey;
    }

    sort($keys);

    return $keys;
}
