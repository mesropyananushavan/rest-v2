<?php

declare(strict_types=1);

use App\Livewire\Admin\OrderBoard;
use App\Livewire\Admin\OrderWorkspace as OrderWorkspaceComponent;
use App\Livewire\Admin\TranslationOverridesEditor;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Identity\Infrastructure\Models\UserBranchAssignment;
use App\Modules\Orders\Contracts\PayableOrderReader;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Orders\Infrastructure\Models\OrderSubtable;
use App\Modules\Payments\Application\CaptureCashPayment;
use App\Modules\Payments\Application\CaptureCashPaymentCommand;
use App\Modules\Payments\Application\CreateCashbox;
use App\Modules\Payments\Application\SelectDefaultCashbox;
use App\Modules\Payments\Infrastructure\Models\Cashbox;
use App\Modules\Payments\Infrastructure\Models\CashboxEntry;
use App\Modules\Payments\Infrastructure\Models\Payment;
use App\Modules\Payments\Infrastructure\Models\PaymentAllocation;
use App\Modules\Tables\Infrastructure\Models\Table as RestaurantTable;
use App\Modules\Tenancy\Application\SuspendOverdueTenantSubscriptions;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Modules\Tenancy\Infrastructure\Models\Branch;
use App\Modules\Tenancy\Infrastructure\Models\Tenant;
use App\Modules\Tenancy\Infrastructure\Models\TenantSubscription;
use App\Support\I18n\TenantTranslationOverride;
use App\Support\I18n\TenantTranslationOverrides;
use App\Support\Logging\LogContext;
use Illuminate\Database\QueryException;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\Support\Jobs\RecordTenantScopedBranchIdsJob;

beforeEach(function (): void {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Runtime database role verification runs only on PostgreSQL.');
    }
});

afterEach(function (): void {
    Cache::flush();
    app(TenantTranslationOverrides::class)->clearRequestCache();
    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();
    app()->setLocale('en');
});

it('uses a restricted non-owning PostgreSQL runtime role', function (): void {
    $expectedUser = (string) config('database.connections.pgsql.username');
    $currentUser = runtimeRoleScalar('select current_user');

    $attributes = DB::selectOne(
        'select rolsuper, rolcreatedb, rolcreaterole, rolbypassrls from pg_roles where rolname = current_user',
    );
    $schemaPrivileges = DB::selectOne(
        "select has_schema_privilege(current_user, 'public', 'USAGE') as can_use_schema, ".
        "has_schema_privilege(current_user, 'public', 'CREATE') as can_create_schema",
    );
    $owners = collect(DB::select(
        "select c.relname, pg_get_userbyid(c.relowner) as owner
         from pg_class c
         join pg_namespace n on n.oid = c.relnamespace
         where n.nspname = 'public'
           and c.relkind in ('r', 'p')
           and c.relname in ('permissions', 'roles', 'role_permissions', 'branches', 'users', 'user_branch_assignments')
         order by c.relname",
    ));
    $memberOfSmartrest = DB::scalar(
        "select case
            when to_regrole('smartrest') is null then false
            else pg_has_role(current_user, 'smartrest', 'member')
        end",
    );
    $inheritedPrivilegedMemberships = collect(DB::select(
        'with recursive memberships as (
             select parent.oid as role_oid, parent.rolname as role_name
             from pg_roles member
             join pg_auth_members auth on auth.member = member.oid
             join pg_roles parent on parent.oid = auth.roleid
             where member.rolname = current_user

             union

             select parent.oid as role_oid, parent.rolname as role_name
             from memberships
             join pg_auth_members auth on auth.member = memberships.role_oid
             join pg_roles parent on parent.oid = auth.roleid
         )
         select distinct roles.rolname, roles.rolsuper, roles.rolbypassrls, roles.rolcreaterole, roles.rolcreatedb
         from memberships
         join pg_roles roles on roles.oid = memberships.role_oid
         where roles.rolname <> current_user
           and (
               roles.rolsuper
               or roles.rolbypassrls
               or roles.rolcreaterole
               or roles.rolcreatedb
           )
         order by roles.rolname',
    ));

    expect($currentUser)->toBe($expectedUser)
        ->and((bool) $attributes->rolsuper)->toBeFalse()
        ->and((bool) $attributes->rolcreatedb)->toBeFalse()
        ->and((bool) $attributes->rolcreaterole)->toBeFalse()
        ->and((bool) $attributes->rolbypassrls)->toBeFalse()
        ->and((bool) $schemaPrivileges->can_use_schema)->toBeTrue()
        ->and((bool) $schemaPrivileges->can_create_schema)->toBeFalse()
        ->and($owners)->toHaveCount(6)
        ->and($owners->pluck('owner')->all())->not->toContain($currentUser)
        ->and((bool) $memberOfSmartrest)->toBeFalse()
        ->and($inheritedPrivilegedMemberships)->toBeEmpty();

    expect(fn (): bool => DB::statement('create table runtime_role_forbidden_ddl (id bigint)'))
        ->toThrow(QueryException::class);
});

it('receives only restricted privileges for future migration-created objects', function (): void {
    $suffix = bin2hex(random_bytes(6));
    $table = "runtime_future_privileges_{$suffix}";
    $sequence = "runtime_future_privileges_{$suffix}_seq";
    $quotedTable = runtimeRoleQuoteIdentifier($table);
    $quotedSequence = runtimeRoleQuoteIdentifier($sequence);
    $privileged = runtimeRolePrivilegedConnection("runtime_privileged_{$suffix}");

    try {
        DB::connection($privileged)->statement("create table {$quotedTable} (id bigint primary key, name text not null)");
        DB::connection($privileged)->statement("create sequence {$quotedSequence}");

        $tablePrivileges = DB::selectOne(
            "select
                has_table_privilege(current_user, ?, 'SELECT') as can_select,
                has_table_privilege(current_user, ?, 'INSERT') as can_insert,
                has_table_privilege(current_user, ?, 'UPDATE') as can_update,
                has_table_privilege(current_user, ?, 'DELETE') as can_delete",
            [$table, $table, $table, $table],
        );
        $sequencePrivileges = DB::selectOne(
            "select
                has_sequence_privilege(current_user, ?, 'USAGE') as can_use,
                has_sequence_privilege(current_user, ?, 'SELECT') as can_select,
                has_sequence_privilege(current_user, ?, 'UPDATE') as can_update",
            [$sequence, $sequence, $sequence],
        );
        $owners = DB::selectOne(
            "select
                pg_get_userbyid(table_class.relowner) as table_owner,
                pg_get_userbyid(sequence_class.relowner) as sequence_owner
             from pg_class table_class
             cross join pg_class sequence_class
             join pg_namespace table_namespace on table_namespace.oid = table_class.relnamespace
             join pg_namespace sequence_namespace on sequence_namespace.oid = sequence_class.relnamespace
             where table_namespace.nspname = 'public'
               and sequence_namespace.nspname = 'public'
               and table_class.relname = ?
               and sequence_class.relname = ?",
            [$table, $sequence],
        );

        expect((bool) $tablePrivileges->can_select)->toBeTrue()
            ->and((bool) $tablePrivileges->can_insert)->toBeTrue()
            ->and((bool) $tablePrivileges->can_update)->toBeTrue()
            ->and((bool) $tablePrivileges->can_delete)->toBeTrue()
            ->and((bool) $sequencePrivileges->can_use)->toBeTrue()
            ->and((bool) $sequencePrivileges->can_select)->toBeTrue()
            ->and((bool) $sequencePrivileges->can_update)->toBeFalse()
            ->and($owners->table_owner)->not->toBe(runtimeRoleScalar('select current_user'))
            ->and($owners->sequence_owner)->not->toBe(runtimeRoleScalar('select current_user'));

        DB::insert("insert into {$quotedTable} (id, name) values (?, ?)", [1, 'created by runtime role']);

        expect(DB::table($table)->where('id', 1)->value('name'))->toBe('created by runtime role')
            ->and(DB::table($table)->where('id', 1)->update(['name' => 'updated by runtime role']))->toBe(1)
            ->and(DB::table($table)->where('id', 1)->delete())->toBe(1)
            ->and((int) DB::scalar("select nextval('{$sequence}'::regclass)"))->toBeGreaterThan(0);

        expect(fn (): bool => DB::statement("alter table {$quotedTable} add column forbidden text"))
            ->toThrow(QueryException::class);
    } finally {
        DB::connection($privileged)->statement("drop table if exists {$quotedTable}");
        DB::connection($privileged)->statement("drop sequence if exists {$quotedSequence}");
        DB::purge($privileged);
    }
});

it('keeps PostgreSQL tenant RLS enforced while allowing same-tenant runtime DML', function (): void {
    $suffix = bin2hex(random_bytes(4));
    $tenantA = Tenant::query()->create([
        'name' => "Runtime Alpha {$suffix}",
        'slug' => "runtime-alpha-{$suffix}",
        'default_locale' => 'en',
        'currency' => 'AMD',
        'status' => 'active',
    ]);
    $tenantB = Tenant::query()->create([
        'name' => "Runtime Beta {$suffix}",
        'slug' => "runtime-beta-{$suffix}",
        'default_locale' => 'en',
        'currency' => 'AMD',
        'status' => 'active',
    ]);

    app(TenantResolver::class)->set((int) $tenantA->id);
    $branchA = Branch::query()->create([
        'name' => "Runtime Alpha Branch {$suffix}",
        'address' => null,
        'phone' => null,
        'locale' => 'en',
        'timezone' => 'Asia/Yerevan',
        'status' => 'active',
    ]);

    app(TenantResolver::class)->set((int) $tenantB->id);
    $branchB = Branch::query()->create([
        'name' => "Runtime Beta Branch {$suffix}",
        'address' => null,
        'phone' => null,
        'locale' => 'en',
        'timezone' => 'Asia/Yerevan',
        'status' => 'active',
    ]);

    app(TenantResolver::class)->set((int) $tenantA->id);

    expect(DB::table('branches')->where('id', (int) $branchA->id)->count())->toBe(1)
        ->and(DB::table('branches')->where('id', (int) $branchB->id)->count())->toBe(0)
        ->and(DB::table('branches')->where('id', (int) $branchA->id)->update(['name' => "Runtime Alpha Updated {$suffix}"]))->toBe(1)
        ->and(DB::table('branches')->where('id', (int) $branchB->id)->update(['name' => "Runtime Beta Leaked {$suffix}"]))->toBe(0);

    expect(fn (): bool => DB::insert(
        'insert into branches (tenant_id, name, locale, timezone, status, created_at, updated_at) values (?, ?, ?, ?, ?, ?, ?)',
        [(int) $tenantB->id, "Runtime Forged {$suffix}", 'en', 'Asia/Yerevan', 'active', now(), now()],
    ))->toThrow(QueryException::class);

    app(TenantResolver::class)->set((int) $tenantB->id);

    expect(Branch::query()->whereKey((int) $branchB->id)->sole()->name)
        ->toBe("Runtime Beta Branch {$suffix}");
});

it('runs translation, orders, queue, and scheduler smoke paths under the runtime role', function (): void {
    $context = runtimeRoleDemoContext();

    $this->actingAs($context['user'])
        ->withSession(['branch_id' => $context['branch_id']])
        ->get(route('admin.translation-overrides.index', ['locale' => 'en', 'q' => 'dashboard']))
        ->assertOk()
        ->assertSeeLivewire(TranslationOverridesEditor::class);

    Livewire::withQueryParams(['locale' => 'en', 'q' => 'dashboard'])
        ->actingAs($context['user'])
        ->test(TranslationOverridesEditor::class)
        ->call('startEditing', 'admin.dashboard.title')
        ->set('overrideValue', 'Runtime Dashboard')
        ->call('save')
        ->assertSet('locale', 'en')
        ->assertSet('search', 'dashboard')
        ->assertSee(__('admin.translation_overrides.flash.saved'), false)
        ->assertSee('Runtime Dashboard', false);

    expect(TenantTranslationOverride::query()
        ->where('locale', 'en')
        ->where('translation_key', 'admin.dashboard.title')
        ->where('override_value', 'Runtime Dashboard')
        ->exists())->toBeTrue();

    $this->actingAs($context['user'])
        ->withSession(['branch_id' => $context['branch_id']])
        ->get(route('admin.orders.board'))
        ->assertOk()
        ->assertSeeLivewire(OrderBoard::class);

    $table = runtimeRoleFreeTable($context['branch_id']);

    Livewire::actingAs($context['user'])
        ->test(OrderBoard::class)
        ->call('selectTable', (int) $table->id)
        ->set('guestCount', 2)
        ->set('comment', 'Runtime role smoke')
        ->call('openOrder')
        ->assertSet('openModalVisible', false);

    $order = Order::query()
        ->where('branch_id', $context['branch_id'])
        ->where('table_id', (int) $table->id)
        ->where('status', 'open')
        ->latest('id')
        ->firstOrFail();

    $this->actingAs($context['user'])
        ->withSession(['branch_id' => $context['branch_id']])
        ->get(route('admin.orders.workspace', ['order' => (int) $order->id]))
        ->assertOk()
        ->assertSeeLivewire(OrderWorkspaceComponent::class);

    Livewire::actingAs($context['user'])
        ->test(OrderWorkspaceComponent::class, ['orderId' => (int) $order->id])
        ->set('newSubtableName', 'Runtime Smoke')
        ->call('createSubtable')
        ->assertSet('statusMessage', __('orders.flash.subtable_added'));

    expect(OrderSubtable::query()
        ->where('order_id', (int) $order->id)
        ->where('name', 'Runtime Smoke')
        ->exists())->toBeTrue();

    Order::query()
        ->whereKey((int) $order->id)
        ->update([
            'subtotal_minor' => 1200,
            'total_minor' => 1200,
        ]);

    DB::transaction(function () use ($order): void {
        $snapshot = app(PayableOrderReader::class)->lockPayableForUpdate((int) $order->id);

        expect($snapshot->orderId)->toBe((int) $order->id)
            ->and($snapshot->totalMinor)->toBe(1200)
            ->and($snapshot->currentRemainingPayableMinor())->toBe(1200);
    });

    LogContext::start('runtime-role-cashbox-smoke', 'payments');
    $cashbox = app(CreateCashbox::class)('Runtime role register');
    app(SelectDefaultCashbox::class)((int) $cashbox->id);

    expect(Cashbox::query()
        ->where('branch_id', $context['branch_id'])
        ->where('name', 'Runtime role register')
        ->where('is_active', true)
        ->where('is_default', true)
        ->exists())->toBeTrue();

    runtimeRoleRunDatabaseQueueSmoke((int) $context['tenant']->id, $context['branch_id'], $context['visible_branch_ids']);

    $this->artisan('schedule:list')->assertSuccessful();
    runtimeRoleRunSchedulerDatabaseSmoke();
});

function runtimeRoleScalar(string $sql): string
{
    $value = DB::scalar($sql);

    expect($value)->toBeString();

    return $value;
}

function runtimeRoleQuoteIdentifier(string $identifier): string
{
    return '"'.str_replace('"', '""', $identifier).'"';
}

function runtimeRolePrivilegedConnection(string $name): string
{
    config([
        "database.connections.{$name}" => array_merge(config('database.connections.pgsql'), [
            'username' => env('DB_MIGRATION_USERNAME', 'smartrest'),
            'password' => env('DB_MIGRATION_PASSWORD', 'smartrest'),
        ]),
    ]);

    DB::purge($name);

    return $name;
}

/**
 * @return array{tenant: Tenant, user: User, branch_id: int, visible_branch_ids: list<int>}
 */
function runtimeRoleDemoContext(): array
{
    $tenant = Tenant::query()->where('slug', 'arat-riverside')->firstOrFail();

    app(TenantResolver::class)->set((int) $tenant->id);

    $user = User::query()->where('email', 'manager@arat.test')->firstOrFail();
    $branchId = UserBranchAssignment::query()
        ->where('user_id', (int) $user->id)
        ->orderBy('branch_id')
        ->value('branch_id');

    expect($branchId)->toBeInt();

    app(BranchContext::class)->set((int) $branchId);
    app()->setLocale('en');

    return [
        'tenant' => $tenant,
        'user' => $user,
        'branch_id' => (int) $branchId,
        'visible_branch_ids' => Branch::query()
            ->orderBy('id')
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all(),
    ];
}

function runtimeRoleFreeTable(int $branchId): RestaurantTable
{
    $occupiedTableIds = Order::query()
        ->where('branch_id', $branchId)
        ->where('status', 'open')
        ->whereNotNull('table_id')
        ->pluck('table_id')
        ->map(fn (mixed $id): int => (int) $id)
        ->all();

    return RestaurantTable::query()
        ->where('branch_id', $branchId)
        ->where('active', true)
        ->where('is_delivery', false)
        ->when($occupiedTableIds !== [], fn ($query) => $query->whereNotIn('id', $occupiedTableIds))
        ->orderBy('id')
        ->firstOrFail();
}

/**
 * @param  list<int>  $visibleBranchIds
 */
function runtimeRoleRunDatabaseQueueSmoke(int $tenantId, int $branchId, array $visibleBranchIds): void
{
    $cacheKey = 'runtime-role-queued-job-result';

    config(['queue.default' => 'database']);
    Cache::forget($cacheKey);

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
        'tenant_id' => $tenantId,
        'branch_id' => $branchId,
        'visible_branch_ids' => $visibleBranchIds,
    ])->and(app(TenantResolver::class)->id())->toBeNull()
        ->and(app(BranchContext::class)->id())->toBeNull();
}

function runtimeRoleRunSchedulerDatabaseSmoke(): void
{
    $suffix = bin2hex(random_bytes(4));
    $tenant = Tenant::query()->create([
        'name' => "Runtime Scheduler {$suffix}",
        'slug' => "runtime-scheduler-{$suffix}",
        'default_locale' => 'en',
        'currency' => 'AMD',
        'status' => 'active',
    ]);
    TenantSubscription::query()->create([
        'tenant_id' => (int) $tenant->id,
        'billing_anchor_day' => 1,
        'next_due_on' => '2026-08-01',
        'grace_days' => 3,
        'last_paid_on' => null,
    ]);

    DB::flushQueryLog();
    DB::enableQueryLog();

    try {
        $result = app(SuspendOverdueTenantSubscriptions::class)(
            new DateTimeImmutable('2026-08-05 05:00:00'),
        );
        $queries = collect(DB::getQueryLog())->pluck('query')->all();
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }

    expect($result->quietHourReached)->toBeTrue()
        ->and($result->candidateCount)->toBeGreaterThanOrEqual(1)
        ->and($result->suspendedCount)->toBeGreaterThanOrEqual(1)
        ->and(Tenant::query()->whereKey((int) $tenant->id)->value('status'))->toBe('suspended')
        ->and(collect($queries)->contains(fn (string $query): bool => str_contains($query, 'tenant_subscriptions')))->toBeTrue()
        ->and(runtimeRoleScalar('select current_user'))->toBe((string) config('database.connections.pgsql.username'));
}

it('captures cash payments through the restricted PostgreSQL runtime role with financial RLS enforced', function (): void {
    $context = runtimeRoleDemoContext();
    auth()->login($context['user']);
    LogContext::start('runtime-role-payment-capture', 'payments');

    $currentUser = runtimeRoleScalar('select current_user');
    $owners = collect(DB::select(
        "select c.relname, pg_get_userbyid(c.relowner) as owner
         from pg_class c
         join pg_namespace n on n.oid = c.relnamespace
         where n.nspname = 'public'
           and c.relname in ('payments', 'payment_allocations', 'cashbox_entries')
         order by c.relname",
    ));

    expect($owners)->toHaveCount(3)
        ->and($owners->pluck('owner')->all())->not->toContain($currentUser);

    $cashbox = app(CreateCashbox::class)('Runtime capture register');
    $order = Order::query()->create([
        'branch_id' => $context['branch_id'],
        'type' => 'fast_food',
        'status' => 'open',
        'table_id' => null,
        'opened_at' => now(),
        'closed_at' => null,
        'client_count' => 1,
        'subtotal_minor' => 4300,
        'discount_minor' => 0,
        'total_minor' => 4300,
        'currency' => 'AMD',
        'comment' => 'Runtime role payment capture',
    ]);

    $result = app(CaptureCashPayment::class)(new CaptureCashPaymentCommand(
        orderId: (int) $order->id,
        cashboxId: (int) $cashbox->id,
        expectedAmountMinor: 4300,
        expectedCurrency: 'AMD',
        idempotencyKey: 'runtime-role-capture-key',
    ));

    expect($result->tenantId)->toBe((int) $context['tenant']->id)
        ->and($result->branchId)->toBe($context['branch_id'])
        ->and($result->orderId)->toBe((int) $order->id)
        ->and($result->cashboxId)->toBe((int) $cashbox->id)
        ->and($result->amountMinor)->toBe(4300)
        ->and($result->currency)->toBe('AMD')
        ->and($result->replayed)->toBeFalse()
        ->and(Payment::query()->whereKey($result->paymentId)->count())->toBe(1)
        ->and(PaymentAllocation::query()->whereKey($result->paymentAllocationId)->count())->toBe(1)
        ->and(CashboxEntry::query()->whereKey($result->cashboxEntryId)->count())->toBe(1)
        ->and(DB::table('audit_logs')->where('action', 'payments.payment.captured')->where('target_id', $result->paymentId)->count())->toBe(1)
        ->and(Order::query()->whereKey((int) $order->id)->value('status'))->toBe('open')
        ->and(Order::query()->whereKey((int) $order->id)->value('closed_at'))->toBeNull();

    $foreignTenant = Tenant::query()->where('id', '<>', (int) $context['tenant']->id)->firstOrFail();
    app(TenantResolver::class)->set((int) $foreignTenant->id);

    expect(Payment::query()->whereKey($result->paymentId)->count())->toBe(0)
        ->and(PaymentAllocation::query()->whereKey($result->paymentAllocationId)->count())->toBe(0)
        ->and(CashboxEntry::query()->whereKey($result->cashboxEntryId)->count())->toBe(0);

    app(TenantResolver::class)->clear();

    expect(Payment::query()->whereKey($result->paymentId)->count())->toBe(0);

    app(TenantResolver::class)->set((int) $foreignTenant->id);

    expect(fn (): bool => DB::insert(
        'insert into payments (tenant_id, branch_id, order_id, cashbox_id, method, status, amount_minor, currency, idempotency_key, idempotency_fingerprint, created_at, updated_at) values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [(int) $context['tenant']->id, $context['branch_id'], (int) $order->id, (int) $cashbox->id, 'cash', 'captured', 4300, 'AMD', 'runtime-forged-payment', hash('sha256', 'runtime-forged-payment'), now(), now()],
    ))->toThrow(QueryException::class);

    expect(runtimeRoleScalar('select current_user'))->toBe($currentUser);
});
