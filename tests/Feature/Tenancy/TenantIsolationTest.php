<?php

declare(strict_types=1);

use App\Modules\Identity\Contracts\Authorizer;
use App\Modules\Identity\Infrastructure\Models\Permission;
use App\Modules\Identity\Infrastructure\Models\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Identity\Infrastructure\Models\UserBranchAssignment;
use App\Modules\Menu\Infrastructure\Models\MenuCategory;
use App\Modules\Menu\Infrastructure\Models\MenuItem;
use App\Modules\Orders\Contracts\PayableOrderReader;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Orders\Infrastructure\Models\OrderItem;
use App\Modules\Orders\Infrastructure\Models\OrderItemMove;
use App\Modules\Orders\Infrastructure\Models\OrderMove;
use App\Modules\Orders\Infrastructure\Models\OrderSubtable;
use App\Modules\Payments\Infrastructure\Models\Cashbox;
use App\Modules\Tables\Infrastructure\Models\Hall;
use App\Modules\Tables\Infrastructure\Models\Table;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Modules\Tenancy\Infrastructure\Models\Branch;
use App\Modules\Tenancy\Infrastructure\Models\Tenant;
use App\Support\Audit\AuditLog;
use App\Support\I18n\TenantTranslationOverride;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Tests\Support\Jobs\RecordTenantScopedBranchIdsJob;

uses(RefreshDatabase::class);

afterEach(function (): void {
    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();
});

it('prevents a user from tenant A from seeing tenant B identity or branch data', function (): void {
    $tenantA = tenantWithUser('tenant-a', 'manager-a', ['menu.items.manage']);
    $tenantB = tenantWithUser('tenant-b', 'manager-b', ['menu.items.manage']);
    $unscopedVisibleRows = usesPostgresRowLevelSecurity() ? 1 : 2;

    app(TenantResolver::class)->set((int) $tenantA['tenant']->id);

    expect(User::query()->pluck('username')->all())->toContain('manager-a')
        ->not->toContain('manager-b')
        ->and(Branch::query()->pluck('name')->all())->toContain('tenant-a Branch')
        ->not->toContain('tenant-b Branch')
        ->and(User::query()->find((int) $tenantB['user']->id))->toBeNull()
        ->and(Branch::query()->find((int) $tenantB['branch']->id))->toBeNull()
        ->and(User::withoutGlobalScopes()->count())->toBe($unscopedVisibleRows)
        ->and(Branch::withoutGlobalScopes()->count())->toBe($unscopedVisibleRows);

    app(TenantResolver::class)->clear();

    expect(User::query()->count())->toBe(0)
        ->and(Branch::query()->count())->toBe(0);
});

it('prevents writes and deletes against another tenant through scoped Eloquent operations', function (): void {
    $tenantA = tenantWithUser('tenant-a', 'manager-a', ['menu.items.manage']);
    $tenantB = tenantWithUser('tenant-b', 'manager-b', ['menu.items.manage']);

    app(TenantResolver::class)->set((int) $tenantA['tenant']->id);

    $updatedByWhere = User::query()
        ->where('id', (int) $tenantB['user']->id)
        ->update(['name' => 'Compromised User']);

    $updatedByWhereKey = Branch::query()
        ->whereKey((int) $tenantB['branch']->id)
        ->update(['name' => 'Compromised Branch']);

    $deletedByWhere = User::query()
        ->where('id', (int) $tenantB['user']->id)
        ->delete();

    $deletedByWhereKey = Branch::query()
        ->whereKey((int) $tenantB['branch']->id)
        ->delete();

    expect($updatedByWhere)->toBe(0)
        ->and($updatedByWhereKey)->toBe(0)
        ->and($deletedByWhere)->toBe(0)
        ->and($deletedByWhereKey)->toBe(0);

    app(TenantResolver::class)->set((int) $tenantB['tenant']->id);

    expect(User::withoutGlobalScopes()->find((int) $tenantB['user']->id)?->name)->toBe('manager-b')
        ->and(Branch::withoutGlobalScopes()->find((int) $tenantB['branch']->id)?->name)->toBe('tenant-b Branch');
});

it('forces created records into the current tenant even when a foreign tenant id is supplied', function (): void {
    $tenantA = tenantWithUser('tenant-a', 'manager-a', ['menu.items.manage']);
    $tenantB = tenantWithUser('tenant-b', 'manager-b', ['menu.items.manage']);

    app(TenantResolver::class)->set((int) $tenantA['tenant']->id);

    $branch = Branch::query()->create([
        'tenant_id' => (int) $tenantB['tenant']->id,
        'name' => 'Tenant Override Attempt',
        'timezone' => 'Asia/Yerevan',
        'status' => 'active',
    ]);

    expect((int) $branch->tenant_id)->toBe((int) $tenantA['tenant']->id)
        ->and(Branch::withoutGlobalScopes()->find((int) $branch->id)?->tenant_id)->toBe($tenantA['tenant']->id);
});

it('returns 404 when an authenticated user requests another tenant resource by id', function (): void {
    $tenantA = tenantWithUser('tenant-a', 'manager-a', ['menu.items.manage']);
    $tenantB = tenantWithUser('tenant-b', 'manager-b', ['menu.items.manage']);

    $this->actingAs($tenantA['user'])
        ->get(route('admin.branches.show', ['branch' => (int) $tenantA['branch']->id]))
        ->assertOk()
        ->assertJsonPath('data.id', (int) $tenantA['branch']->id);

    $this->actingAs($tenantA['user'])
        ->get(route('admin.branches.show', ['branch' => (int) $tenantB['branch']->id]))
        ->assertNotFound();
});

it('resolves tenant and branch context from request headers', function (): void {
    $tenant = tenantWithUser('tenant-a', 'manager-a', ['menu.items.manage']);

    Route::middleware('web')->get('/_test/context', fn () => response()->json([
        'tenant_id' => app(TenantResolver::class)->id(),
        'branch_id' => app(BranchContext::class)->id(),
        'locale' => app()->getLocale(),
    ]));

    $this->withHeader('X-Tenant-ID', (string) $tenant['tenant']->id)
        ->withHeader('X-Branch-ID', (string) $tenant['branch']->id)
        ->get('/_test/context')
        ->assertOk()
        ->assertJson([
            'tenant_id' => (int) $tenant['tenant']->id,
            'branch_id' => (int) $tenant['branch']->id,
            'locale' => 'hy',
        ]);
});

it('ignores tenant header in production', function (): void {
    $tenant = tenantWithUser('tenant-a', 'manager-a', ['menu.items.manage']);

    app()->detectEnvironment(fn (): string => 'production');

    Route::middleware('web')->get('/_test/production-context', fn () => response()->json([
        'tenant_id' => app(TenantResolver::class)->id(),
    ]));

    $this->withHeader('X-Tenant-ID', (string) $tenant['tenant']->id)
        ->get('/_test/production-context')
        ->assertOk()
        ->assertJson([
            'tenant_id' => null,
        ]);
});

it('does not allow tenant header to override an authenticated user tenant', function (): void {
    $tenantA = tenantWithUser('tenant-a', 'manager-a', ['menu.items.manage']);
    $tenantB = tenantWithUser('tenant-b', 'manager-b', ['menu.items.manage']);

    Route::middleware(['web', 'auth'])->get('/_test/authenticated-context', fn () => response()->json([
        'tenant_id' => app(TenantResolver::class)->id(),
    ]));

    $this->actingAs($tenantA['user'])
        ->withHeader('X-Tenant-ID', (string) $tenantB['tenant']->id)
        ->get('/_test/authenticated-context')
        ->assertOk()
        ->assertJson([
            'tenant_id' => (int) $tenantA['tenant']->id,
        ]);
});

it('restores tenant and branch context for tenant-scoped queries inside queued jobs', function (): void {
    $tenantA = tenantWithUser('tenant-a', 'manager-a', ['menu.items.manage']);
    $tenantB = tenantWithUser('tenant-b', 'manager-b', ['menu.items.manage']);
    $cacheKey = 'tenant-scoped-queued-job-result';

    config(['queue.default' => 'database']);
    app(TenantResolver::class)->set((int) $tenantA['tenant']->id);
    app(BranchContext::class)->set((int) $tenantA['branch']->id);

    Queue::connection('database')->push(new RecordTenantScopedBranchIdsJob($cacheKey));

    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();

    expect(Branch::query()->count())->toBe(0);

    $job = Queue::connection('database')->pop('default');

    expect($job)->not->toBeNull();
    assert($job !== null);

    Event::dispatch(new JobProcessing('database', $job));
    $job->fire();
    Event::dispatch(new JobProcessed('database', $job));

    expect(Cache::get($cacheKey))->toBe([
        'tenant_id' => (int) $tenantA['tenant']->id,
        'branch_id' => (int) $tenantA['branch']->id,
        'visible_branch_ids' => [(int) $tenantA['branch']->id],
    ])->and(app(TenantResolver::class)->id())->toBeNull()
        ->and(app(BranchContext::class)->id())->toBeNull();
});

it('enforces PostgreSQL row level security when tenant setting is missing', function (): void {
    if (! usesPostgresRowLevelSecurity()) {
        $this->markTestSkipped('PostgreSQL RLS coverage runs only on pgsql.');
    }

    $tenantA = tenantWithUser('tenant-a', 'manager-a', ['menu.items.manage']);
    $tenantB = tenantWithUser('tenant-b', 'manager-b', ['menu.items.manage']);

    app(TenantResolver::class)->clear();

    expect(rawBranchIds())->toBe([]);

    app(TenantResolver::class)->set((int) $tenantA['tenant']->id);

    expect(rawBranchIds())->toBe([(int) $tenantA['branch']->id]);

    app(TenantResolver::class)->set((int) $tenantB['tenant']->id);

    expect(rawBranchIds())->toBe([(int) $tenantB['branch']->id]);
});

it('backfills orders cancel permission for every tenant under PostgreSQL row level security', function (): void {
    if (! usesPostgresRowLevelSecurity()) {
        $this->markTestSkipped('PostgreSQL RLS coverage runs only on pgsql.');
    }

    $tenantIds = [];

    foreach ([
        'legacy-alpha' => [
            'owner' => false,
            'manager' => false,
            'administrator' => true,
            'cashier' => false,
            'waiter' => false,
        ],
        'legacy-beta' => [
            'owner' => false,
            'manager' => false,
            'branch-lead' => true,
            'cashier' => false,
            'waiter' => false,
        ],
        'custom-only' => [
            'administrator' => false,
        ],
    ] as $slug => $roleClassifications) {
        $tenant = Tenant::query()->create([
            'name' => str($slug)->headline()->toString(),
            'slug' => $slug,
            'default_locale' => 'en',
            'currency' => 'AMD',
            'status' => 'active',
        ]);
        $tenantIds[$slug] = (int) $tenant->id;

        app(TenantResolver::class)->set((int) $tenant->id);

        $takePermission = Permission::query()->create([
            'code' => 'orders.take',
            'name' => 'Take orders',
        ]);

        foreach ($roleClassifications as $roleCode => $isManagementRole) {
            $role = Role::query()->create([
                'code' => $roleCode,
                'name' => str($roleCode)->headline()->toString(),
                'is_management_role' => $isManagementRole,
            ]);

            $role->permissions()->attach((int) $takePermission->id, [
                'tenant_id' => (int) $tenant->id,
            ]);
        }
    }

    app(TenantResolver::class)->set($tenantIds['legacy-alpha']);

    $migration = require database_path('migrations/2026_08_03_000000_backfill_orders_cancel_permission.php');
    $migration->up();
    $migration->up();

    expect(app(TenantResolver::class)->id())->toBe($tenantIds['legacy-alpha']);
    expect(currentPostgresTenantSetting())->toBe((string) $tenantIds['legacy-alpha']);

    app(TenantResolver::class)->set($tenantIds['legacy-alpha']);

    expect(DB::table('permissions')->where('code', 'orders.cancel')->count())->toBe(1)
        ->and(rolesWithOrdersCancelPermission())->toBe(['administrator']);

    app(TenantResolver::class)->set($tenantIds['legacy-beta']);

    expect(DB::table('permissions')->where('code', 'orders.cancel')->count())->toBe(1)
        ->and(rolesWithOrdersCancelPermission())->toBe(['branch-lead']);

    app(TenantResolver::class)->set($tenantIds['custom-only']);

    expect(DB::table('permissions')->where('code', 'orders.cancel')->count())->toBe(1)
        ->and(rolesWithOrdersCancelPermission())->toBe([]);
});

it('enforces PostgreSQL row level security for identity users by selected tenant context', function (): void {
    if (! usesPostgresRowLevelSecurity()) {
        $this->markTestSkipped('PostgreSQL RLS coverage runs only on pgsql.');
    }

    $tenantA = tenantWithUser('tenant-a', 'shared-user', ['menu.items.manage']);
    $tenantB = tenantWithUser('tenant-b', 'shared-user', ['menu.items.manage']);

    app(TenantResolver::class)->clear();

    expect(rawUserEmails())->toBe([]);

    app(TenantResolver::class)->set((int) $tenantA['tenant']->id);

    expect(rawUserEmails())->toBe(['shared-user@smartrest.test'])
        ->and(rawUserIds())->toBe([(int) $tenantA['user']->id]);

    app(TenantResolver::class)->set((int) $tenantB['tenant']->id);

    expect(rawUserEmails())->toBe(['shared-user@smartrest.test'])
        ->and(rawUserIds())->toBe([(int) $tenantB['user']->id]);
});

it('enforces PostgreSQL row level security for cashbox select insert and update operations', function (): void {
    if (! usesPostgresRowLevelSecurity()) {
        $this->markTestSkipped('PostgreSQL RLS coverage runs only on pgsql.');
    }

    $tenantA = tenantWithUser('tenant-cashbox-a', 'cashbox-manager-a', ['payments.cashboxes.manage']);
    $tenantB = tenantWithUser('tenant-cashbox-b', 'cashbox-manager-b', ['payments.cashboxes.manage']);

    app(TenantResolver::class)->set((int) $tenantA['tenant']->id);
    $cashboxA = Cashbox::query()->create([
        'branch_id' => (int) $tenantA['branch']->id,
        'name' => 'Tenant A Register',
        'is_active' => true,
        'is_default' => true,
    ]);

    app(TenantResolver::class)->set((int) $tenantB['tenant']->id);
    $cashboxB = Cashbox::query()->create([
        'branch_id' => (int) $tenantB['branch']->id,
        'name' => 'Tenant B Register',
        'is_active' => true,
        'is_default' => true,
    ]);

    app(TenantResolver::class)->clear();

    expect(rawCashboxIds())->toBe([]);

    app(TenantResolver::class)->set((int) $tenantA['tenant']->id);

    expect(rawCashboxIds())->toBe([(int) $cashboxA->id])
        ->and(DB::table('cashboxes')->where('id', (int) $cashboxA->id)->update(['name' => 'Tenant A Updated']))->toBe(1)
        ->and(DB::table('cashboxes')->where('id', (int) $cashboxB->id)->update(['name' => 'Tenant B Leaked']))->toBe(0);

    DB::statement('SAVEPOINT cashbox_rls_insert');

    try {
        DB::insert(
            'insert into cashboxes (tenant_id, branch_id, name, is_active, is_default, created_at, updated_at) values (?, ?, ?, ?, ?, ?, ?)',
            [(int) $tenantB['tenant']->id, (int) $tenantB['branch']->id, 'Forged Register', true, false, now(), now()],
        );
        $this->fail('Expected PostgreSQL RLS to reject the forged cashbox insert.');
    } catch (QueryException) {
        expect(true)->toBeTrue();
    } finally {
        DB::statement('ROLLBACK TO SAVEPOINT cashbox_rls_insert');
        DB::statement('RELEASE SAVEPOINT cashbox_rls_insert');
    }

    app(TenantResolver::class)->set((int) $tenantB['tenant']->id);

    expect(rawCashboxIds())->toBe([(int) $cashboxB->id])
        ->and(Cashbox::query()->findOrFail((int) $cashboxB->id)->name)->toBe('Tenant B Register');
});

it('enforces PostgreSQL row level security and insert consistency for payment financial tables', function (): void {
    if (! usesPostgresRowLevelSecurity()) {
        $this->markTestSkipped('PostgreSQL RLS coverage runs only on pgsql.');
    }

    expect(paymentFinancialTablesForceRowLevelSecurity())->toBe([
        'cashbox_entries' => true,
        'payment_allocations' => true,
        'payments' => true,
    ])->and(paymentFinancialTenantPolicies())->toBe([
        'cashbox_entries_tenant_isolation',
        'payment_allocations_tenant_isolation',
        'payments_tenant_isolation',
    ])->and(paymentFinancialTriggerNames())->toBe([
        'cashbox_entries_insert_consistency',
        'cashbox_entries_no_delete',
        'cashbox_entries_no_update',
        'payment_allocations_insert_consistency',
        'payment_allocations_no_delete',
        'payment_allocations_no_update',
        'payments_insert_consistency',
        'payments_no_delete',
        'payments_no_update',
    ]);

    $tenantA = tenantWithUser('tenant-financial-a', 'financial-manager-a', ['payments.cashboxes.manage']);
    $tenantB = tenantWithUser('tenant-financial-b', 'financial-manager-b', ['payments.cashboxes.manage']);

    app(TenantResolver::class)->set((int) $tenantA['tenant']->id);
    app(BranchContext::class)->set((int) $tenantA['branch']->id);
    $cashboxA = Cashbox::query()->create([
        'branch_id' => (int) $tenantA['branch']->id,
        'name' => 'Tenant A Register',
        'is_active' => true,
        'is_default' => true,
    ]);
    $orderA = Order::query()->create([
        'branch_id' => (int) $tenantA['branch']->id,
        'type' => 'fast_food',
        'status' => 'open',
        'opened_at' => now(),
        'client_count' => 1,
        'subtotal_minor' => 5000,
        'discount_minor' => 0,
        'total_minor' => 5000,
        'currency' => 'AMD',
    ]);
    $paymentA = insertPaymentFinancialRecord($tenantA, (int) $orderA->id, (int) $cashboxA->id, 5000, 'tenant-a-payment');
    $allocationA = insertPaymentAllocationRecord($tenantA, $paymentA, (int) $orderA->id, 5000);
    $entryA = insertCashboxEntryRecord($tenantA, (int) $cashboxA->id, $paymentA, 5000);

    app(TenantResolver::class)->set((int) $tenantB['tenant']->id);
    app(BranchContext::class)->set((int) $tenantB['branch']->id);
    $cashboxB = Cashbox::query()->create([
        'branch_id' => (int) $tenantB['branch']->id,
        'name' => 'Tenant B Register',
        'is_active' => true,
        'is_default' => true,
    ]);
    $orderB = Order::query()->create([
        'branch_id' => (int) $tenantB['branch']->id,
        'type' => 'fast_food',
        'status' => 'open',
        'opened_at' => now(),
        'client_count' => 1,
        'subtotal_minor' => 7000,
        'discount_minor' => 0,
        'total_minor' => 7000,
        'currency' => 'AMD',
    ]);
    $paymentB = insertPaymentFinancialRecord($tenantB, (int) $orderB->id, (int) $cashboxB->id, 7000, 'tenant-b-payment');
    $allocationB = insertPaymentAllocationRecord($tenantB, $paymentB, (int) $orderB->id, 7000);
    $entryB = insertCashboxEntryRecord($tenantB, (int) $cashboxB->id, $paymentB, 7000);

    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();

    expect(rawPaymentFinancialIds('payments'))->toBe([])
        ->and(rawPaymentFinancialIds('payment_allocations'))->toBe([])
        ->and(rawPaymentFinancialIds('cashbox_entries'))->toBe([]);

    app(TenantResolver::class)->set((int) $tenantA['tenant']->id);

    expect(rawPaymentFinancialIds('payments'))->toBe([$paymentA])
        ->and(rawPaymentFinancialIds('payment_allocations'))->toBe([$allocationA])
        ->and(rawPaymentFinancialIds('cashbox_entries'))->toBe([$entryA]);

    DB::statement('SAVEPOINT payment_financial_order_consistency');

    try {
        insertPaymentFinancialRecord($tenantA, (int) $orderB->id, (int) $cashboxA->id, 1000, 'forged-order-payment');
        $this->fail('Expected PostgreSQL consistency trigger to reject a cross-tenant order payment.');
    } catch (QueryException) {
        expect(true)->toBeTrue();
    } finally {
        DB::statement('ROLLBACK TO SAVEPOINT payment_financial_order_consistency');
        DB::statement('RELEASE SAVEPOINT payment_financial_order_consistency');
    }

    DB::statement('SAVEPOINT payment_financial_allocation_consistency');

    try {
        $overPayment = insertPaymentFinancialRecord($tenantA, (int) $orderA->id, (int) $cashboxA->id, 1, 'overpaid-order-payment');
        insertPaymentAllocationRecord($tenantA, $overPayment, (int) $orderA->id, 1);
        $this->fail('Expected PostgreSQL consistency trigger to reject allocations above the order total.');
    } catch (QueryException) {
        expect(true)->toBeTrue();
    } finally {
        DB::statement('ROLLBACK TO SAVEPOINT payment_financial_allocation_consistency');
        DB::statement('RELEASE SAVEPOINT payment_financial_allocation_consistency');
    }

    DB::statement('SAVEPOINT payment_financial_cashbox_entry_consistency');

    try {
        insertCashboxEntryRecord($tenantA, (int) $cashboxA->id, $paymentA, 1, 'mismatched-payment-entry');
        $this->fail('Expected PostgreSQL consistency trigger to reject a mismatched payment cashbox entry.');
    } catch (QueryException) {
        expect(true)->toBeTrue();
    } finally {
        DB::statement('ROLLBACK TO SAVEPOINT payment_financial_cashbox_entry_consistency');
        DB::statement('RELEASE SAVEPOINT payment_financial_cashbox_entry_consistency');
    }

    app(TenantResolver::class)->set((int) $tenantB['tenant']->id);

    expect(rawPaymentFinancialIds('payments'))->toBe([$paymentB])
        ->and(rawPaymentFinancialIds('payment_allocations'))->toBe([$allocationB])
        ->and(rawPaymentFinancialIds('cashbox_entries'))->toBe([$entryB]);
});

it('keeps PostgreSQL tenant slug login query shape bounded with unrelated tenants', function (): void {
    if (! usesPostgresRowLevelSecurity()) {
        $this->markTestSkipped('PostgreSQL query-shape coverage runs only on pgsql.');
    }

    for ($tenant = 1; $tenant <= 20; $tenant++) {
        tenantWithUser("noise-login-tenant-{$tenant}", "noise-login-user-{$tenant}", ['menu.items.manage']);
    }

    $record = tenantWithUser('tenant-login-target', 'tenant-login-manager', ['menu.items.manage']);

    DB::flushQueryLog();
    DB::enableQueryLog();

    $this->withSession(['_token' => 'tenant-login-token'])
        ->post(route('login.store'), [
            '_token' => 'tenant-login-token',
            'tenant_slug' => 'tenant-login-target',
            'email' => 'tenant-login-manager@smartrest.test',
            'password' => 'password',
        ])
        ->assertRedirect(route('admin.dashboard'))
        ->assertSessionHas('tenant_id', (int) $record['tenant']->id)
        ->assertSessionMissing('branch_id');

    $queries = collect(DB::getQueryLog())->pluck('query');

    expect($queries->filter(fn (string $query): bool => str_contains($query, 'from "tenants"'))->count())->toBe(1)
        ->and($queries->filter(fn (string $query): bool => str_contains($query, 'from "users"'))->count())->toBe(1)
        ->and($queries->count())->toBeLessThanOrEqual(8);
});

it('enforces PostgreSQL row level security for menu tables', function (): void {
    if (! usesPostgresRowLevelSecurity()) {
        $this->markTestSkipped('PostgreSQL RLS coverage runs only on pgsql.');
    }

    $tenantA = tenantWithUser('tenant-a', 'manager-a', ['menu.items.manage']);
    $tenantB = tenantWithUser('tenant-b', 'manager-b', ['menu.items.manage']);

    app(TenantResolver::class)->set((int) $tenantA['tenant']->id);
    app(BranchContext::class)->set((int) $tenantA['branch']->id);

    $rootCategoryA = MenuCategory::query()->create([
        'translated_name' => ['hy' => 'Tenant A Menu', 'ru' => 'Tenant A Menu', 'en' => 'Tenant A Menu'],
        'sort_order' => 0,
        'active' => true,
    ]);

    $categoryA = MenuCategory::query()->create([
        'parent_id' => (int) $rootCategoryA->id,
        'translated_name' => ['hy' => 'Tenant A', 'ru' => 'Tenant A', 'en' => 'Tenant A'],
        'sort_order' => 10,
        'active' => true,
    ]);

    $itemA = MenuItem::query()->create([
        'branch_id' => (int) $tenantA['branch']->id,
        'category_id' => (int) $categoryA->id,
        'translated_name' => ['hy' => 'Tenant A Item', 'ru' => 'Tenant A Item', 'en' => 'Tenant A Item'],
        'translated_description' => ['hy' => 'Tenant A Description', 'ru' => 'Tenant A Description', 'en' => 'Tenant A Description'],
        'price_minor' => 100000,
        'currency' => 'AMD',
        'active' => true,
    ]);

    app(TenantResolver::class)->set((int) $tenantB['tenant']->id);
    app(BranchContext::class)->set((int) $tenantB['branch']->id);

    $rootCategoryB = MenuCategory::query()->create([
        'translated_name' => ['hy' => 'Tenant B Menu', 'ru' => 'Tenant B Menu', 'en' => 'Tenant B Menu'],
        'sort_order' => 0,
        'active' => true,
    ]);

    $categoryB = MenuCategory::query()->create([
        'parent_id' => (int) $rootCategoryB->id,
        'translated_name' => ['hy' => 'Tenant B', 'ru' => 'Tenant B', 'en' => 'Tenant B'],
        'sort_order' => 10,
        'active' => true,
    ]);

    $itemB = MenuItem::query()->create([
        'branch_id' => (int) $tenantB['branch']->id,
        'category_id' => (int) $categoryB->id,
        'translated_name' => ['hy' => 'Tenant B Item', 'ru' => 'Tenant B Item', 'en' => 'Tenant B Item'],
        'translated_description' => ['hy' => 'Tenant B Description', 'ru' => 'Tenant B Description', 'en' => 'Tenant B Description'],
        'price_minor' => 200000,
        'currency' => 'AMD',
        'active' => true,
    ]);

    app(TenantResolver::class)->clear();

    expect(rawMenuItemIds())->toBe([]);

    app(TenantResolver::class)->set((int) $tenantA['tenant']->id);

    expect(rawMenuItemIds())->toBe([(int) $itemA->id]);

    app(TenantResolver::class)->set((int) $tenantB['tenant']->id);

    expect(rawMenuItemIds())->toBe([(int) $itemB->id]);
});

it('enforces PostgreSQL row level security for audit logs', function (): void {
    if (! usesPostgresRowLevelSecurity()) {
        $this->markTestSkipped('PostgreSQL RLS coverage runs only on pgsql.');
    }

    $tenantA = tenantWithUser('tenant-a', 'manager-a', ['menu.items.manage']);
    $tenantB = tenantWithUser('tenant-b', 'manager-b', ['menu.items.manage']);

    app(TenantResolver::class)->set((int) $tenantA['tenant']->id);
    app(BranchContext::class)->set((int) $tenantA['branch']->id);

    $auditA = AuditLog::query()->create([
        'branch_id' => (int) $tenantA['branch']->id,
        'actor_id' => (int) $tenantA['user']->id,
        'action' => 'menu.item.created',
        'target_type' => 'menu_item',
        'target_id' => 1,
        'before_json' => null,
        'after_json' => ['tenant' => 'a'],
        'correlation_id' => 'tenant-a-audit',
        'ip_address' => null,
    ]);

    app(TenantResolver::class)->set((int) $tenantB['tenant']->id);
    app(BranchContext::class)->set((int) $tenantB['branch']->id);

    $auditB = AuditLog::query()->create([
        'branch_id' => (int) $tenantB['branch']->id,
        'actor_id' => (int) $tenantB['user']->id,
        'action' => 'menu.item.created',
        'target_type' => 'menu_item',
        'target_id' => 2,
        'before_json' => null,
        'after_json' => ['tenant' => 'b'],
        'correlation_id' => 'tenant-b-audit',
        'ip_address' => null,
    ]);

    app(TenantResolver::class)->clear();

    expect(rawAuditLogIds())->toBe([]);

    app(TenantResolver::class)->set((int) $tenantA['tenant']->id);

    expect(rawAuditLogIds())->toBe([(int) $auditA->id]);

    app(TenantResolver::class)->set((int) $tenantB['tenant']->id);

    expect(rawAuditLogIds())->toBe([(int) $auditB->id]);
});

it('enforces PostgreSQL row level security for tenant translation overrides', function (): void {
    if (! usesPostgresRowLevelSecurity()) {
        $this->markTestSkipped('PostgreSQL RLS coverage runs only on pgsql.');
    }

    $tenantA = tenantWithUser('tenant-a', 'manager-a', ['menu.items.manage']);
    $tenantB = tenantWithUser('tenant-b', 'manager-b', ['menu.items.manage']);

    app(TenantResolver::class)->set((int) $tenantA['tenant']->id);

    $overrideA = TenantTranslationOverride::query()->create([
        'locale' => 'hy',
        'translation_key' => 'admin.dashboard.title',
        'override_value' => 'Tenant A dashboard',
    ]);

    app(TenantResolver::class)->set((int) $tenantB['tenant']->id);

    $overrideB = TenantTranslationOverride::query()->create([
        'locale' => 'hy',
        'translation_key' => 'admin.dashboard.title',
        'override_value' => 'Tenant B dashboard',
    ]);

    app(TenantResolver::class)->clear();

    expect(rawTenantTranslationOverrideIds())->toBe([]);

    app(TenantResolver::class)->set((int) $tenantA['tenant']->id);

    expect(rawTenantTranslationOverrideIds())->toBe([(int) $overrideA->id]);

    app(TenantResolver::class)->set((int) $tenantB['tenant']->id);

    expect(rawTenantTranslationOverrideIds())->toBe([(int) $overrideB->id]);
});

it('enforces PostgreSQL row level security for halls', function (): void {
    if (! usesPostgresRowLevelSecurity()) {
        $this->markTestSkipped('PostgreSQL RLS coverage runs only on pgsql.');
    }

    $tenantA = tenantWithUser('tenant-a', 'manager-a', ['tables.halls.manage']);
    $tenantB = tenantWithUser('tenant-b', 'manager-b', ['tables.halls.manage']);

    app(TenantResolver::class)->set((int) $tenantA['tenant']->id);
    app(BranchContext::class)->set((int) $tenantA['branch']->id);

    $hallA = Hall::query()->create([
        'branch_id' => (int) $tenantA['branch']->id,
        'translated_name' => ['hy' => 'Tenant A Hall', 'ru' => 'Tenant A Hall', 'en' => 'Tenant A Hall'],
        'color' => '#5FA8D3',
        'sort_order' => 10,
        'active' => true,
    ]);

    app(TenantResolver::class)->set((int) $tenantB['tenant']->id);
    app(BranchContext::class)->set((int) $tenantB['branch']->id);

    $hallB = Hall::query()->create([
        'branch_id' => (int) $tenantB['branch']->id,
        'translated_name' => ['hy' => 'Tenant B Hall', 'ru' => 'Tenant B Hall', 'en' => 'Tenant B Hall'],
        'color' => '#D36B5F',
        'sort_order' => 10,
        'active' => true,
    ]);

    app(TenantResolver::class)->clear();

    expect(rawHallIds())->toBe([]);

    app(TenantResolver::class)->set((int) $tenantA['tenant']->id);

    expect(rawHallIds())->toBe([(int) $hallA->id]);

    app(TenantResolver::class)->set((int) $tenantB['tenant']->id);

    expect(rawHallIds())->toBe([(int) $hallB->id]);
});

it('enforces PostgreSQL row level security for tables', function (): void {
    if (! usesPostgresRowLevelSecurity()) {
        $this->markTestSkipped('PostgreSQL RLS coverage runs only on pgsql.');
    }

    $tenantA = tenantWithUser('tenant-a', 'manager-a', ['tables.halls.manage', 'tables.tables.manage']);
    $tenantB = tenantWithUser('tenant-b', 'manager-b', ['tables.halls.manage', 'tables.tables.manage']);

    app(TenantResolver::class)->set((int) $tenantA['tenant']->id);
    app(BranchContext::class)->set((int) $tenantA['branch']->id);

    $hallA = Hall::query()->create([
        'branch_id' => (int) $tenantA['branch']->id,
        'translated_name' => ['hy' => 'Tenant A Hall', 'ru' => 'Tenant A Hall', 'en' => 'Tenant A Hall'],
        'color' => '#5FA8D3',
        'sort_order' => 10,
        'active' => true,
    ]);

    $tableA = Table::query()->create([
        'branch_id' => (int) $tenantA['branch']->id,
        'hall_id' => (int) $hallA->id,
        'translated_name' => ['hy' => 'Tenant A Table', 'ru' => 'Tenant A Table', 'en' => 'Tenant A Table'],
        'type' => 'standard',
        'shape' => 'square',
        'hdm_department' => 1,
        'is_delivery' => false,
        'sort_order' => 10,
        'active' => true,
    ]);

    app(TenantResolver::class)->set((int) $tenantB['tenant']->id);
    app(BranchContext::class)->set((int) $tenantB['branch']->id);

    $hallB = Hall::query()->create([
        'branch_id' => (int) $tenantB['branch']->id,
        'translated_name' => ['hy' => 'Tenant B Hall', 'ru' => 'Tenant B Hall', 'en' => 'Tenant B Hall'],
        'color' => '#D36B5F',
        'sort_order' => 10,
        'active' => true,
    ]);

    $tableB = Table::query()->create([
        'branch_id' => (int) $tenantB['branch']->id,
        'hall_id' => (int) $hallB->id,
        'translated_name' => ['hy' => 'Tenant B Table', 'ru' => 'Tenant B Table', 'en' => 'Tenant B Table'],
        'type' => 'vip',
        'shape' => 'rectangle',
        'hdm_department' => 1,
        'is_delivery' => false,
        'sort_order' => 10,
        'active' => true,
    ]);

    app(TenantResolver::class)->clear();

    expect(rawTableIds())->toBe([]);

    app(TenantResolver::class)->set((int) $tenantA['tenant']->id);

    expect(rawTableIds())->toBe([(int) $tableA->id]);

    app(TenantResolver::class)->set((int) $tenantB['tenant']->id);

    expect(rawTableIds())->toBe([(int) $tableB->id]);
});

it('enforces PostgreSQL row level security for orders and order subtables', function (): void {
    if (! usesPostgresRowLevelSecurity()) {
        $this->markTestSkipped('PostgreSQL RLS coverage runs only on pgsql.');
    }

    $tenantA = tenantWithUser('tenant-a', 'manager-a', ['orders.take']);
    $tenantB = tenantWithUser('tenant-b', 'manager-b', ['orders.take']);

    app(TenantResolver::class)->set((int) $tenantA['tenant']->id);
    app(BranchContext::class)->set((int) $tenantA['branch']->id);

    $hallA = Hall::query()->create([
        'branch_id' => (int) $tenantA['branch']->id,
        'translated_name' => ['hy' => 'Tenant A Hall', 'ru' => 'Tenant A Hall', 'en' => 'Tenant A Hall'],
        'color' => '#5FA8D3',
        'sort_order' => 10,
        'active' => true,
    ]);

    $tableA = Table::query()->create([
        'branch_id' => (int) $tenantA['branch']->id,
        'hall_id' => (int) $hallA->id,
        'translated_name' => ['hy' => 'Tenant A Table', 'ru' => 'Tenant A Table', 'en' => 'Tenant A Table'],
        'type' => 'standard',
        'shape' => 'square',
        'hdm_department' => 1,
        'is_delivery' => false,
        'sort_order' => 10,
        'active' => true,
    ]);

    $targetTableA = Table::query()->create([
        'branch_id' => (int) $tenantA['branch']->id,
        'hall_id' => (int) $hallA->id,
        'translated_name' => ['hy' => 'Tenant A Target Table', 'ru' => 'Tenant A Target Table', 'en' => 'Tenant A Target Table'],
        'type' => 'standard',
        'shape' => 'square',
        'hdm_department' => 1,
        'is_delivery' => false,
        'sort_order' => 20,
        'active' => true,
    ]);

    $orderA = Order::query()->create([
        'branch_id' => (int) $tenantA['branch']->id,
        'type' => 'dine_in',
        'status' => 'open',
        'table_id' => (int) $tableA->id,
        'opened_at' => now(),
        'client_count' => 1,
        'subtotal_minor' => 0,
        'discount_minor' => 0,
        'total_minor' => 0,
        'currency' => 'AMD',
    ]);

    $subtableA = OrderSubtable::query()->create([
        'branch_id' => (int) $tenantA['branch']->id,
        'order_id' => (int) $orderA->id,
        'name' => 'Tenant A Subtable',
        'status' => 'open',
    ]);

    $itemA = OrderItem::query()->create([
        'branch_id' => (int) $tenantA['branch']->id,
        'order_id' => (int) $orderA->id,
        'subtable_id' => (int) $subtableA->id,
        'menu_item_id' => 1001,
        'qty' => 1,
        'unit_price_minor' => 1000,
        'discount_minor' => 0,
        'total_minor' => 1000,
        'currency' => 'AMD',
        'seller_id' => (int) $tenantA['user']->id,
        'preparation_status' => 'pending',
    ]);

    $moveA = OrderItemMove::query()->create([
        'branch_id' => (int) $tenantA['branch']->id,
        'order_item_id' => (int) $itemA->id,
        'source_order_id' => (int) $orderA->id,
        'target_order_id' => (int) $orderA->id,
        'source_subtable_id' => (int) $subtableA->id,
        'target_subtable_id' => null,
        'actor_id' => (int) $tenantA['user']->id,
        'reason' => 'Tenant A move',
    ]);

    $orderMoveA = OrderMove::query()->create([
        'branch_id' => (int) $tenantA['branch']->id,
        'order_id' => (int) $orderA->id,
        'source_table_id' => (int) $tableA->id,
        'target_table_id' => (int) $targetTableA->id,
        'actor_id' => (int) $tenantA['user']->id,
        'reason' => 'Tenant A order move',
    ]);

    app(TenantResolver::class)->set((int) $tenantB['tenant']->id);
    app(BranchContext::class)->set((int) $tenantB['branch']->id);

    $hallB = Hall::query()->create([
        'branch_id' => (int) $tenantB['branch']->id,
        'translated_name' => ['hy' => 'Tenant B Hall', 'ru' => 'Tenant B Hall', 'en' => 'Tenant B Hall'],
        'color' => '#D36B5F',
        'sort_order' => 10,
        'active' => true,
    ]);

    $tableB = Table::query()->create([
        'branch_id' => (int) $tenantB['branch']->id,
        'hall_id' => (int) $hallB->id,
        'translated_name' => ['hy' => 'Tenant B Table', 'ru' => 'Tenant B Table', 'en' => 'Tenant B Table'],
        'type' => 'standard',
        'shape' => 'square',
        'hdm_department' => 1,
        'is_delivery' => false,
        'sort_order' => 10,
        'active' => true,
    ]);

    $targetTableB = Table::query()->create([
        'branch_id' => (int) $tenantB['branch']->id,
        'hall_id' => (int) $hallB->id,
        'translated_name' => ['hy' => 'Tenant B Target Table', 'ru' => 'Tenant B Target Table', 'en' => 'Tenant B Target Table'],
        'type' => 'standard',
        'shape' => 'square',
        'hdm_department' => 1,
        'is_delivery' => false,
        'sort_order' => 20,
        'active' => true,
    ]);

    $orderB = Order::query()->create([
        'branch_id' => (int) $tenantB['branch']->id,
        'type' => 'dine_in',
        'status' => 'open',
        'table_id' => (int) $tableB->id,
        'opened_at' => now(),
        'client_count' => 1,
        'subtotal_minor' => 0,
        'discount_minor' => 0,
        'total_minor' => 0,
        'currency' => 'AMD',
    ]);

    $subtableB = OrderSubtable::query()->create([
        'branch_id' => (int) $tenantB['branch']->id,
        'order_id' => (int) $orderB->id,
        'name' => 'Tenant B Subtable',
        'status' => 'open',
    ]);

    $itemB = OrderItem::query()->create([
        'branch_id' => (int) $tenantB['branch']->id,
        'order_id' => (int) $orderB->id,
        'subtable_id' => (int) $subtableB->id,
        'menu_item_id' => 2001,
        'qty' => 1,
        'unit_price_minor' => 2000,
        'discount_minor' => 0,
        'total_minor' => 2000,
        'currency' => 'AMD',
        'seller_id' => (int) $tenantB['user']->id,
        'preparation_status' => 'pending',
    ]);

    $moveB = OrderItemMove::query()->create([
        'branch_id' => (int) $tenantB['branch']->id,
        'order_item_id' => (int) $itemB->id,
        'source_order_id' => (int) $orderB->id,
        'target_order_id' => (int) $orderB->id,
        'source_subtable_id' => (int) $subtableB->id,
        'target_subtable_id' => null,
        'actor_id' => (int) $tenantB['user']->id,
        'reason' => 'Tenant B move',
    ]);

    $orderMoveB = OrderMove::query()->create([
        'branch_id' => (int) $tenantB['branch']->id,
        'order_id' => (int) $orderB->id,
        'source_table_id' => (int) $tableB->id,
        'target_table_id' => (int) $targetTableB->id,
        'actor_id' => (int) $tenantB['user']->id,
        'reason' => 'Tenant B order move',
    ]);

    app(TenantResolver::class)->clear();

    expect(rawOrderIds())->toBe([])
        ->and(rawOrderSubtableIds())->toBe([])
        ->and(rawOrderItemIds())->toBe([])
        ->and(rawOrderItemMoveIds())->toBe([])
        ->and(rawOrderMoveIds())->toBe([]);

    app(TenantResolver::class)->set((int) $tenantA['tenant']->id);

    expect(rawOrderIds())->toBe([(int) $orderA->id])
        ->and(rawOrderSubtableIds())->toBe([(int) $subtableA->id])
        ->and(rawOrderItemIds())->toBe([(int) $itemA->id])
        ->and(rawOrderItemMoveIds())->toBe([(int) $moveA->id])
        ->and(rawOrderMoveIds())->toBe([(int) $orderMoveA->id]);

    app(TenantResolver::class)->set((int) $tenantB['tenant']->id);

    expect(rawOrderIds())->toBe([(int) $orderB->id])
        ->and(rawOrderSubtableIds())->toBe([(int) $subtableB->id])
        ->and(rawOrderItemIds())->toBe([(int) $itemB->id])
        ->and(rawOrderItemMoveIds())->toBe([(int) $moveB->id])
        ->and(rawOrderMoveIds())->toBe([(int) $orderMoveB->id]);

    app(TenantResolver::class)->set((int) $tenantA['tenant']->id);

    expect(fn () => DB::transaction(fn (): bool => DB::insert(
        'insert into orders (tenant_id, branch_id, type, status, opened_at, client_count, subtotal_minor, discount_minor, total_minor, currency, created_at, updated_at) values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [
            (int) $tenantB['tenant']->id,
            (int) $tenantB['branch']->id,
            'fast_food',
            'open',
            now(),
            1,
            0,
            0,
            0,
            'AMD',
            now(),
            now(),
        ],
    )))->toThrow(QueryException::class);

    expect(fn () => DB::transaction(fn (): bool => DB::insert(
        'insert into order_subtables (tenant_id, branch_id, order_id, name, status, created_at, updated_at) values (?, ?, ?, ?, ?, ?, ?)',
        [
            (int) $tenantB['tenant']->id,
            (int) $tenantB['branch']->id,
            (int) $orderB->id,
            'Blocked Subtable',
            'open',
            now(),
            now(),
        ],
    )))->toThrow(QueryException::class);

    expect(fn () => DB::transaction(fn (): bool => DB::insert(
        'insert into order_items (tenant_id, branch_id, order_id, menu_item_id, qty, unit_price_minor, discount_minor, total_minor, currency, seller_id, preparation_status, created_at, updated_at) values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [
            (int) $tenantB['tenant']->id,
            (int) $tenantB['branch']->id,
            (int) $orderB->id,
            2002,
            1,
            2000,
            0,
            2000,
            'AMD',
            (int) $tenantB['user']->id,
            'pending',
            now(),
            now(),
        ],
    )))->toThrow(QueryException::class);

    expect(fn () => DB::transaction(fn (): bool => DB::insert(
        'insert into order_item_moves (tenant_id, branch_id, order_item_id, source_order_id, target_order_id, source_subtable_id, target_subtable_id, actor_id, reason, created_at, updated_at) values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [
            (int) $tenantB['tenant']->id,
            (int) $tenantB['branch']->id,
            (int) $itemB->id,
            (int) $orderB->id,
            (int) $orderB->id,
            (int) $subtableB->id,
            null,
            (int) $tenantB['user']->id,
            'Blocked Move',
            now(),
            now(),
        ],
    )))->toThrow(QueryException::class);

    expect(fn () => DB::transaction(fn (): bool => DB::insert(
        'insert into order_moves (tenant_id, branch_id, order_id, source_table_id, target_table_id, actor_id, reason, created_at, updated_at) values (?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [
            (int) $tenantB['tenant']->id,
            (int) $tenantB['branch']->id,
            (int) $orderB->id,
            (int) $tableB->id,
            (int) $targetTableB->id,
            (int) $tenantB['user']->id,
            'Blocked Order Move',
            now(),
            now(),
        ],
    )))->toThrow(QueryException::class);
});

it('enforces PostgreSQL row level security for payable order reads', function (): void {
    if (! usesPostgresRowLevelSecurity()) {
        $this->markTestSkipped('PostgreSQL RLS coverage runs only on pgsql.');
    }

    $tenantA = tenantWithUser('tenant-payable-a', 'manager-payable-a', ['orders.take']);
    $tenantB = tenantWithUser('tenant-payable-b', 'manager-payable-b', ['orders.take']);

    app(TenantResolver::class)->set((int) $tenantA['tenant']->id);
    $orderA = Order::query()->create([
        'branch_id' => (int) $tenantA['branch']->id,
        'type' => 'fast_food',
        'status' => 'open',
        'opened_at' => now(),
        'client_count' => 1,
        'subtotal_minor' => 2100,
        'discount_minor' => 0,
        'total_minor' => 2100,
        'currency' => 'AMD',
    ]);

    app(TenantResolver::class)->set((int) $tenantB['tenant']->id);
    $orderB = Order::query()->create([
        'branch_id' => (int) $tenantB['branch']->id,
        'type' => 'fast_food',
        'status' => 'open',
        'opened_at' => now(),
        'client_count' => 1,
        'subtotal_minor' => 3200,
        'discount_minor' => 0,
        'total_minor' => 3200,
        'currency' => 'AMD',
    ]);

    app(TenantResolver::class)->clear();
    app(BranchContext::class)->clear();

    expect(DB::table('orders')->pluck('id')->all())->toBe([]);

    app(TenantResolver::class)->set((int) $tenantA['tenant']->id);
    app(BranchContext::class)->set((int) $tenantA['branch']->id);

    expect(DB::table('orders')->where('id', (int) $orderA->id)->count())->toBe(1)
        ->and(DB::table('orders')->where('id', (int) $orderB->id)->count())->toBe(0)
        ->and(app(PayableOrderReader::class)->findPayable((int) $orderA->id)->totalMinor)->toBe(2100)
        ->and(fn () => app(PayableOrderReader::class)->findPayable((int) $orderB->id))
        ->toThrow(ModelNotFoundException::class);
});

it('checks action permissions through the identity authorizer contract', function (): void {
    $tenant = tenantWithUser('tenant-a', 'manager-a', ['menu.items.manage']);

    app(TenantResolver::class)->set((int) $tenant['tenant']->id);

    expect(app(Authorizer::class)->allows($tenant['user'], 'menu.items.manage'))->toBeTrue()
        ->and(app(Authorizer::class)->allows($tenant['user'], 'identity.manage'))->toBeFalse()
        ->and(Gate::forUser($tenant['user'])->allows('menu.items.manage'))->toBeTrue()
        ->and(Gate::forUser($tenant['user'])->allows('identity.manage'))->toBeFalse();
});

it('keeps the explicit superadmin authorizer bypass for purpose-made users', function (): void {
    $tenant = tenantWithUser('tenant-a', 'support-operator', [], superadmin: true);

    app(TenantResolver::class)->set((int) $tenant['tenant']->id);

    expect($tenant['user']->is_superadmin)->toBeTrue()
        ->and(app(Authorizer::class)->allows($tenant['user'], 'identity.manage'))->toBeTrue()
        ->and(Gate::forUser($tenant['user'])->allows('identity.manage'))->toBeTrue();
});

/**
 * @param  list<string>  $permissionCodes
 * @return array{tenant: Tenant, branch: Branch, role: Role, user: User}
 */
function tenantWithUser(string $slug, string $username, array $permissionCodes, bool $superadmin = false): array
{
    $tenant = Tenant::query()->create([
        'name' => str($slug)->headline()->toString(),
        'slug' => $slug,
        'default_locale' => 'hy',
        'currency' => 'AMD',
        'status' => 'active',
    ]);

    app(TenantResolver::class)->set((int) $tenant->id);

    $branch = Branch::query()->create([
        'name' => "{$slug} Branch",
        'timezone' => 'Asia/Yerevan',
        'status' => 'active',
    ]);

    app(BranchContext::class)->set((int) $branch->id);

    $role = Role::query()->create([
        'code' => "{$slug}-manager",
        'name' => "{$slug} Manager",
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
        'default_locale' => 'hy',
        'active' => true,
        'is_superadmin' => $superadmin,
        'password' => Hash::make('password'),
    ]);

    UserBranchAssignment::query()->create([
        'user_id' => (int) $user->id,
        'branch_id' => (int) $branch->id,
    ]);

    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();

    return [
        'tenant' => $tenant,
        'branch' => $branch,
        'role' => $role,
        'user' => $user,
    ];
}

function usesPostgresRowLevelSecurity(): bool
{
    return DB::connection()->getDriverName() === 'pgsql';
}

/**
 * @return list<int>
 */
function rawBranchIds(): array
{
    return collect(DB::select('select id from branches order by id'))
        ->map(fn (object $row): int => (int) $row->id)
        ->all();
}

/**
 * @return list<int>
 */
function rawUserIds(): array
{
    return collect(DB::select('select id from users order by id'))
        ->map(fn (object $row): int => (int) $row->id)
        ->all();
}

/**
 * @return list<string>
 */
function rawUserEmails(): array
{
    return collect(DB::select('select email from users order by id'))
        ->map(fn (object $row): string => (string) $row->email)
        ->all();
}

/**
 * @return list<string>
 */
function rolesWithOrdersCancelPermission(): array
{
    return collect(DB::select(<<<'SQL'
        select roles.code
        from roles
        join role_permissions on role_permissions.role_id = roles.id
        join permissions on permissions.id = role_permissions.permission_id
        where permissions.code = 'orders.cancel'
        order by roles.code
        SQL))
        ->map(fn (object $row): string => (string) $row->code)
        ->all();
}

function currentPostgresTenantSetting(): string
{
    $row = DB::selectOne("select current_setting('smartrest.tenant_id', true) as tenant_id");

    return (string) ($row?->tenant_id ?? '');
}

/**
 * @return list<int>
 */
function rawMenuItemIds(): array
{
    return collect(DB::select('select id from menu_items order by id'))
        ->map(fn (object $row): int => (int) $row->id)
        ->all();
}

/**
 * @return list<int>
 */
function rawAuditLogIds(): array
{
    return collect(DB::select('select id from audit_logs order by id'))
        ->map(fn (object $row): int => (int) $row->id)
        ->all();
}

/**
 * @return list<int>
 */
function rawTenantTranslationOverrideIds(): array
{
    return collect(DB::select('select id from tenant_translation_overrides order by id'))
        ->map(fn (object $row): int => (int) $row->id)
        ->all();
}

/**
 * @return list<int>
 */
function rawHallIds(): array
{
    return collect(DB::select('select id from halls order by id'))
        ->map(fn (object $row): int => (int) $row->id)
        ->all();
}

/**
 * @return list<int>
 */
function rawTableIds(): array
{
    return collect(DB::select('select id from tables order by id'))
        ->map(fn (object $row): int => (int) $row->id)
        ->all();
}

/**
 * @return list<int>
 */
function rawCashboxIds(): array
{
    return collect(DB::select('select id from cashboxes order by id'))
        ->map(fn (object $row): int => (int) $row->id)
        ->all();
}

/**
 * @return list<int>
 */
function rawPaymentFinancialIds(string $table): array
{
    return collect(DB::select("select id from {$table} order by id"))
        ->map(fn (object $row): int => (int) $row->id)
        ->all();
}

/**
 * @return list<int>
 */
function rawOrderIds(): array
{
    return collect(DB::select('select id from orders order by id'))
        ->map(fn (object $row): int => (int) $row->id)
        ->all();
}

/**
 * @return list<int>
 */
function rawOrderSubtableIds(): array
{
    return collect(DB::select('select id from order_subtables order by id'))
        ->map(fn (object $row): int => (int) $row->id)
        ->all();
}

/**
 * @return list<int>
 */
function rawOrderItemIds(): array
{
    return collect(DB::select('select id from order_items order by id'))
        ->map(fn (object $row): int => (int) $row->id)
        ->all();
}

/**
 * @return list<int>
 */
function rawOrderItemMoveIds(): array
{
    return collect(DB::select('select id from order_item_moves order by id'))
        ->map(fn (object $row): int => (int) $row->id)
        ->all();
}

/**
 * @return list<int>
 */
function rawOrderMoveIds(): array
{
    return collect(DB::select('select id from order_moves order by id'))
        ->map(fn (object $row): int => (int) $row->id)
        ->all();
}

/**
 * @return array<string, bool>
 */
function paymentFinancialTablesForceRowLevelSecurity(): array
{
    return collect(DB::select(<<<'SQL'
        select relname, relforcerowsecurity
        from pg_class
        where relname in ('payments', 'payment_allocations', 'cashbox_entries')
        order by relname
        SQL))
        ->mapWithKeys(fn (object $row): array => [(string) $row->relname => (bool) $row->relforcerowsecurity])
        ->all();
}

/**
 * @return list<string>
 */
function paymentFinancialTenantPolicies(): array
{
    return collect(DB::select(<<<'SQL'
        select policyname
        from pg_policies
        where tablename in ('payments', 'payment_allocations', 'cashbox_entries')
        order by policyname
        SQL))
        ->map(fn (object $row): string => (string) $row->policyname)
        ->all();
}

/**
 * @return list<string>
 */
function paymentFinancialTriggerNames(): array
{
    return collect(DB::select(<<<'SQL'
        select trigger_name
        from information_schema.triggers
        where event_object_table in ('payments', 'payment_allocations', 'cashbox_entries')
        order by trigger_name
        SQL))
        ->map(fn (object $row): string => (string) $row->trigger_name)
        ->all();
}

/**
 * @param  array{tenant: Tenant, branch: Branch, role: Role, user: User}  $tenant
 */
function insertPaymentFinancialRecord(array $tenant, int $orderId, int $cashboxId, int $amountMinor, string $idempotencyKey): int
{
    return (int) DB::table('payments')->insertGetId([
        'tenant_id' => (int) $tenant['tenant']->id,
        'branch_id' => (int) $tenant['branch']->id,
        'order_id' => $orderId,
        'cashbox_id' => $cashboxId,
        'method' => 'cash',
        'status' => 'captured',
        'amount_minor' => $amountMinor,
        'currency' => 'AMD',
        'idempotency_key' => $idempotencyKey,
        'idempotency_fingerprint' => hash('sha256', $idempotencyKey),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/**
 * @param  array{tenant: Tenant, branch: Branch, role: Role, user: User}  $tenant
 */
function insertPaymentAllocationRecord(array $tenant, int $paymentId, int $orderId, int $amountMinor): int
{
    return (int) DB::table('payment_allocations')->insertGetId([
        'tenant_id' => (int) $tenant['tenant']->id,
        'branch_id' => (int) $tenant['branch']->id,
        'payment_id' => $paymentId,
        'payable_type' => 'order',
        'payable_id' => $orderId,
        'amount_minor' => $amountMinor,
        'currency' => 'AMD',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/**
 * @param  array{tenant: Tenant, branch: Branch, role: Role, user: User}  $tenant
 */
function insertCashboxEntryRecord(
    array $tenant,
    int $cashboxId,
    int $paymentId,
    int $amountMinor,
    string $reason = 'payment_capture',
): int {
    return (int) DB::table('cashbox_entries')->insertGetId([
        'tenant_id' => (int) $tenant['tenant']->id,
        'branch_id' => (int) $tenant['branch']->id,
        'cashbox_id' => $cashboxId,
        'direction' => 'in',
        'amount_minor' => $amountMinor,
        'currency' => 'AMD',
        'reason' => $reason,
        'source_type' => 'payment',
        'source_id' => $paymentId,
        'posted_by_id' => (int) $tenant['user']->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}
