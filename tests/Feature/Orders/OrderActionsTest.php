<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Models\Permission;
use App\Modules\Identity\Infrastructure\Models\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Identity\Infrastructure\Models\UserBranchAssignment;
use App\Modules\Orders\Application\AddSubtable;
use App\Modules\Orders\Application\AssignWaiter;
use App\Modules\Orders\Application\CancelOrder;
use App\Modules\Orders\Application\FindOrder;
use App\Modules\Orders\Application\ListOpenOrders;
use App\Modules\Orders\Application\MoveOrder;
use App\Modules\Orders\Application\OpenOrder;
use App\Modules\Orders\Application\OpenTablelessOrder;
use App\Modules\Orders\Domain\OrdersDomainException;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Orders\Infrastructure\Models\OrderMove;
use App\Modules\Orders\Infrastructure\Models\OrderSubtable;
use App\Modules\Tables\Infrastructure\Models\Hall;
use App\Modules\Tables\Infrastructure\Models\Table;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Modules\Tenancy\Infrastructure\Models\Branch;
use App\Modules\Tenancy\Infrastructure\Models\Tenant;
use App\Support\Audit\AuditLog;
use App\Support\Logging\LogContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

afterEach(function (): void {
    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();
    LogContext::clear();
});

it('opens assigns subtables reads and cancels dine-in orders with audit rows', function (): void {
    $record = ordersActionsUser('tenant-a', 'manager-a', branchCount: 2);
    $table = ordersActionsTable($record, 0, 'Table 1');
    $hiddenBranchTable = ordersActionsTable($record, 1, 'Branch 2 Table');
    $waiter = ordersActionsStaffUser($record, 0, 'assignable-waiter', ['orders.take']);

    ordersActionsActingIn($record, 0, 'orders-actions-request');

    $order = app(OpenOrder::class)((int) $table->id, clientCount: 3, comment: 'Window seat');

    expect((int) $order->branch_id)->toBe((int) $record['branches'][0]->id)
        ->and((int) $order->table_id)->toBe((int) $table->id)
        ->and((int) $order->waiter_id)->toBe((int) $record['user']->id)
        ->and($order->type)->toBe('dine_in')
        ->and($order->status)->toBe('open')
        ->and($order->subtotal()->minor)->toBe(0)
        ->and($order->discount()->minor)->toBe(0)
        ->and($order->total()->minor)->toBe(0)
        ->and($order->total()->currency)->toBe('AMD');

    $createdAudit = AuditLog::query()->where('action', 'orders.order.opened')->firstOrFail();

    expect($createdAudit->tenant_id)->toBe((int) $record['tenant']->id)
        ->and($createdAudit->branch_id)->toBe((int) $record['branches'][0]->id)
        ->and($createdAudit->actor_id)->toBe((int) $record['user']->id)
        ->and($createdAudit->target_type)->toBe('orders_order')
        ->and($createdAudit->target_id)->toBe((int) $order->id)
        ->and($createdAudit->correlation_id)->toBe('orders-actions-request')
        ->and($createdAudit->before_json)->toBeNull()
        ->and($createdAudit->after_json['table_id'])->toBe((int) $table->id)
        ->and($createdAudit->after_json['client_count'])->toBe(3)
        ->and($createdAudit->after_json['subtotal_minor'])->toBe(0);

    expect(fn () => app(OpenOrder::class)((int) $table->id))
        ->toThrow(OrdersDomainException::class, 'This table already has an open order.');

    try {
        app(OpenOrder::class)((int) $table->id);
    } catch (OrdersDomainException $exception) {
        expect($exception->errorCode())->toBe('orders.table_already_open');
    }

    expect(fn () => app(OpenOrder::class)((int) $hiddenBranchTable->id))
        ->toThrow(OrdersDomainException::class, 'The selected table is not available in the current branch.');

    $assigned = app(AssignWaiter::class)((int) $order->id, (int) $waiter->id);
    $cleared = app(AssignWaiter::class)((int) $order->id, null);
    $subtable = app(AddSubtable::class)((int) $order->id, 'Guest 1');
    $found = app(FindOrder::class)((int) $order->id);
    $openOrders = app(ListOpenOrders::class)(perPage: 25);

    expect((int) $assigned->waiter_id)->toBe((int) $waiter->id)
        ->and($cleared->waiter_id)->toBeNull()
        ->and((int) $subtable->order_id)->toBe((int) $order->id)
        ->and($subtable->name)->toBe('Guest 1')
        ->and($found->subtables)->toHaveCount(1)
        ->and($openOrders->total())->toBe(1)
        ->and($openOrders->items()[0]->id)->toBe((int) $order->id);

    $cancelled = app(CancelOrder::class)((int) $order->id);

    expect($cancelled->status)->toBe('cancelled')
        ->and($cancelled->closed_at)->not->toBeNull();

    $reopened = app(OpenOrder::class)((int) $table->id);

    expect((int) $reopened->table_id)->toBe((int) $table->id)
        ->and($reopened->status)->toBe('open');

    expect(AuditLog::query()->where('target_type', 'orders_order')->orderBy('id')->pluck('action')->all())->toBe([
        'orders.order.opened',
        'orders.order.waiter_assigned',
        'orders.order.waiter_assigned',
        'orders.order.cancelled',
        'orders.order.opened',
    ])->and(AuditLog::query()->where('target_type', 'orders_subtable')->pluck('action')->all())->toBe([
        'orders.subtable.added',
    ]);
});

it('rejects assigning waiters that cannot take orders in the current tenant branch', function (): void {
    $tenantA = ordersActionsUser('tenant-a', 'manager-a', branchCount: 2);
    $tenantB = ordersActionsUser('tenant-b', 'manager-b');

    $wrongBranch = ordersActionsStaffUser($tenantA, 1, 'wrong-branch-waiter', ['orders.take']);
    $inactive = ordersActionsStaffUser($tenantA, 0, 'inactive-waiter', ['orders.take'], active: false);
    $noPermission = ordersActionsStaffUser($tenantA, 0, 'viewer-a', []);
    $foreign = ordersActionsStaffUser($tenantB, 0, 'foreign-waiter', ['orders.take']);
    $table = ordersActionsTable($tenantA, 0, 'Assign Guard Table');

    ordersActionsActingIn($tenantA, 0, 'orders-assign-invalid');
    $order = app(OpenOrder::class)((int) $table->id);
    $initialWaiterId = (int) $order->waiter_id;

    Log::spy();

    foreach ([
        'wrong branch' => $wrongBranch,
        'inactive' => $inactive,
        'no permission' => $noPermission,
        'foreign tenant' => $foreign,
    ] as $case => $waiter) {
        try {
            app(AssignWaiter::class)((int) $order->id, (int) $waiter->id);
        } catch (OrdersDomainException $exception) {
            expect($exception->errorCode(), $case)->toBe('orders.waiter_not_assignable');

            continue;
        }

        throw new RuntimeException("Expected waiter rejection for {$case}.");
    }

    $freshOrder = Order::query()->findOrFail((int) $order->id);

    expect((int) $freshOrder->waiter_id)->toBe($initialWaiterId)
        ->and(AuditLog::query()->where('action', 'orders.order.waiter_assigned')->count())->toBe(0);

    Log::shouldHaveReceived('warning')
        ->with('action failed', Mockery::on(fn (array $context): bool => ($context['action'] ?? null) === 'orders.assign_waiter'
            && ($context['error_code'] ?? null) === 'orders.waiter_not_assignable'))
        ->times(4);
});

it('allows assigning an active branch assigned superadmin through the effective authorization model', function (): void {
    $record = ordersActionsUser('tenant-a', 'manager-a');
    $waiter = ordersActionsStaffUser($record, 0, 'break-glass-waiter', [], superadmin: true);
    $table = ordersActionsTable($record, 0, 'Superadmin Assign Table');

    ordersActionsActingIn($record, 0, 'orders-assign-superadmin');
    $order = app(OpenOrder::class)((int) $table->id);

    expect($waiter->can('orders.take'))->toBeTrue();

    $assigned = app(AssignWaiter::class)((int) $order->id, (int) $waiter->id);

    expect((int) $assigned->waiter_id)->toBe((int) $waiter->id)
        ->and((int) Order::query()->findOrFail((int) $order->id)->waiter_id)->toBe((int) $waiter->id);
});

it('requires branch context when assigning a waiter', function (): void {
    $record = ordersActionsUser('tenant-a', 'manager-a');
    $table = ordersActionsTable($record, 0, 'Assign Branch Context Table');

    ordersActionsActingIn($record, 0, 'orders-assign-branch-context-source');
    $order = app(OpenOrder::class)((int) $table->id);

    app(BranchContext::class)->clear();
    Log::spy();

    try {
        app(AssignWaiter::class)((int) $order->id, null);
    } catch (OrdersDomainException $exception) {
        expect($exception->errorCode())->toBe('orders.branch_context_required')
            ->and(AuditLog::query()->where('action', 'orders.order.waiter_assigned')->count())->toBe(0);

        Log::shouldHaveReceived('warning')
            ->with('action failed', Mockery::on(fn (array $context): bool => ($context['action'] ?? null) === 'orders.assign_waiter'
                && ($context['error_code'] ?? null) === 'orders.branch_context_required'))
            ->once();

        return;
    }

    throw new RuntimeException('Expected AssignWaiter branch context guard to throw.');
});

it('uses tenant settings currency and tenant scoped models do not leak records', function (): void {
    $tenantA = ordersActionsUser('tenant-a', 'manager-a', currency: 'USD');
    $tenantB = ordersActionsUser('tenant-b', 'manager-b', currency: 'AMD');
    $tableA = ordersActionsTable($tenantA, 0, 'Tenant A Table');
    $tableB = ordersActionsTable($tenantB, 0, 'Tenant B Table');

    ordersActionsActingIn($tenantA, 0, 'orders-tenant-a');
    $orderA = app(OpenOrder::class)((int) $tableA->id);
    app(AddSubtable::class)((int) $orderA->id, 'Tenant A Subtable');

    ordersActionsActingIn($tenantB, 0, 'orders-tenant-b');
    $orderB = app(OpenOrder::class)((int) $tableB->id);

    app(TenantResolver::class)->set((int) $tenantA['tenant']->id);
    app(BranchContext::class)->set((int) $tenantA['branches'][0]->id);

    expect($orderA->total()->currency)->toBe('USD')
        ->and(Order::query()->pluck('id')->all())->toBe([(int) $orderA->id])
        ->and(OrderSubtable::query()->pluck('order_id')->all())->toBe([(int) $orderA->id])
        ->and(Order::query()->find((int) $orderB->id))->toBeNull();

    app(TenantResolver::class)->clear();

    expect(Order::query()->count())->toBe(0)
        ->and(OrderSubtable::query()->count())->toBe(0);
});

it('guards mutations to open orders only', function (): void {
    $record = ordersActionsUser('tenant-a', 'manager-a');
    $table = ordersActionsTable($record, 0, 'Table 1');

    ordersActionsActingIn($record, 0, 'orders-guards');
    $order = app(OpenOrder::class)((int) $table->id);
    app(CancelOrder::class)((int) $order->id);

    foreach ([
        fn () => app(CancelOrder::class)((int) $order->id),
        fn () => app(AssignWaiter::class)((int) $order->id, (int) $record['user']->id),
        fn () => app(AddSubtable::class)((int) $order->id, 'Late guest'),
    ] as $callback) {
        try {
            $callback();
        } catch (OrdersDomainException $exception) {
            expect($exception->errorCode())->toBe('orders.order_not_open');

            continue;
        }

        throw new RuntimeException('Expected open-order guard to throw.');
    }
});

it('moves an open dine-in order to a free table and records audit history', function (): void {
    $record = ordersActionsUser('tenant-a', 'manager-a');
    $sourceTable = ordersActionsTable($record, 0, 'Source Table');
    $targetTable = ordersActionsTable($record, 0, 'Target Table');

    ordersActionsActingIn($record, 0, 'orders-move-happy');

    $order = app(OpenOrder::class)((int) $sourceTable->id);
    $order->update([
        'subtotal_minor' => 3200,
        'discount_minor' => 200,
        'total_minor' => 3000,
    ]);
    $order->refresh();

    $moved = app(MoveOrder::class)((int) $order->id, (int) $targetTable->id, 'guest requested a quieter table');
    $fresh = Order::query()->findOrFail((int) $order->id);
    $move = OrderMove::query()->sole();
    $audit = AuditLog::query()
        ->where('action', 'orders.order.moved')
        ->where('target_type', 'orders_order')
        ->sole();

    expect((int) $moved->table_id)->toBe((int) $targetTable->id)
        ->and((int) $fresh->table_id)->toBe((int) $targetTable->id)
        ->and((int) $fresh->subtotal_minor)->toBe(3200)
        ->and((int) $fresh->discount_minor)->toBe(200)
        ->and((int) $fresh->total_minor)->toBe(3000)
        ->and((int) $move->branch_id)->toBe((int) $record['branches'][0]->id)
        ->and((int) $move->order_id)->toBe((int) $order->id)
        ->and((int) $move->source_table_id)->toBe((int) $sourceTable->id)
        ->and((int) $move->target_table_id)->toBe((int) $targetTable->id)
        ->and((int) $move->actor_id)->toBe((int) $record['user']->id)
        ->and($move->reason)->toBe('guest requested a quieter table')
        ->and((int) $audit->target_id)->toBe((int) $order->id)
        ->and($audit->before_json['table_id'])->toBe((int) $sourceTable->id)
        ->and($audit->after_json['table_id'])->toBe((int) $targetTable->id)
        ->and($audit->after_json['total_minor'])->toBe(3000);
});

it('rejects invalid whole-order moves without recording move history', function (): void {
    $record = ordersActionsUser('tenant-a', 'manager-a', branchCount: 2);
    $sourceTable = ordersActionsTable($record, 0, 'Source Table');
    $targetTable = ordersActionsTable($record, 0, 'Target Table');
    $occupiedTable = ordersActionsTable($record, 0, 'Occupied Table');
    $closedTable = ordersActionsTable($record, 0, 'Closed Table');
    $foreignBranchTable = ordersActionsTable($record, 1, 'Foreign Branch Table');

    ordersActionsActingIn($record, 0, 'orders-move-guards');

    $sourceOrder = app(OpenOrder::class)((int) $sourceTable->id);
    $occupiedOrder = app(OpenOrder::class)((int) $occupiedTable->id);
    $closedOrder = app(OpenOrder::class)((int) $closedTable->id);
    app(CancelOrder::class)((int) $closedOrder->id);
    $tablelessOrder = app(OpenTablelessOrder::class)('fast_food');

    foreach ([
        [fn () => app(MoveOrder::class)((int) $closedOrder->id, (int) $targetTable->id), 'orders.order_not_open'],
        [fn () => app(MoveOrder::class)((int) $tablelessOrder->id, (int) $targetTable->id), 'orders.invalid_order_type'],
        [fn () => app(MoveOrder::class)((int) $sourceOrder->id, 999999), 'orders.table_not_found'],
        [fn () => app(MoveOrder::class)((int) $sourceOrder->id, (int) $foreignBranchTable->id), 'orders.table_not_found'],
        [fn () => app(MoveOrder::class)((int) $sourceOrder->id, (int) $occupiedTable->id), 'orders.table_already_open'],
        [fn () => app(MoveOrder::class)((int) $sourceOrder->id, (int) $sourceTable->id), 'orders.order_move_noop'],
    ] as [$callback, $code]) {
        ordersActionsExpectMoveDomainCode($callback, $code);

        expect(OrderMove::query()->count())->toBe(0)
            ->and((int) Order::query()->findOrFail((int) $sourceOrder->id)->table_id)->toBe((int) $sourceTable->id)
            ->and((int) Order::query()->findOrFail((int) $occupiedOrder->id)->table_id)->toBe((int) $occupiedTable->id);
    }
});

it('requires tenant and branch context when moving whole orders', function (): void {
    $record = ordersActionsUser('tenant-a', 'manager-a');
    $sourceTable = ordersActionsTable($record, 0, 'Source Table');
    $targetTable = ordersActionsTable($record, 0, 'Target Table');

    ordersActionsActingIn($record, 0, 'orders-move-context-source');
    $order = app(OpenOrder::class)((int) $sourceTable->id);

    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();

    ordersActionsExpectMoveDomainCode(
        fn () => app(MoveOrder::class)((int) $order->id, (int) $targetTable->id),
        'orders.tenant_context_required',
    );

    app(TenantResolver::class)->set((int) $record['tenant']->id);

    ordersActionsExpectMoveDomainCode(
        fn () => app(MoveOrder::class)((int) $order->id, (int) $targetTable->id),
        'orders.branch_context_required',
    );

    expect(OrderMove::query()->count())->toBe(0);
});

/**
 * @return array{tenant: Tenant, branches: list<Branch>, user: User}
 */
function ordersActionsUser(string $tenantSlug, string $username, int $branchCount = 1, string $currency = 'AMD'): array
{
    $tenant = Tenant::query()->create([
        'name' => str($tenantSlug)->headline()->toString(),
        'slug' => $tenantSlug,
        'default_locale' => 'en',
        'currency' => $currency,
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

    foreach ($branches as $branch) {
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
 * @param  list<string>  $permissionCodes
 */
function ordersActionsStaffUser(array $record, int $branchIndex, string $username, array $permissionCodes, bool $active = true, bool $superadmin = false): User
{
    app(TenantResolver::class)->set((int) $record['tenant']->id);
    app(BranchContext::class)->set((int) $record['branches'][$branchIndex]->id);

    $role = Role::query()->create([
        'code' => "{$username}-role",
        'name' => "{$username} Role",
    ]);

    $permissions = collect($permissionCodes)
        ->map(fn (string $code): Permission => Permission::query()->firstOrCreate(
            ['code' => $code],
            ['name' => $code],
        ));

    if ($permissions->isNotEmpty()) {
        $role->permissions()->attach(
            $permissions->pluck('id')->all(),
            ['tenant_id' => (int) $record['tenant']->id],
        );
    }

    $user = User::query()->create([
        'role_id' => (int) $role->id,
        'name' => $username,
        'email' => "{$username}@smartrest.test",
        'username' => $username,
        'default_locale' => 'en',
        'active' => $active,
        'is_superadmin' => $superadmin,
        'password' => Hash::make('password'),
    ]);

    UserBranchAssignment::query()->create([
        'user_id' => (int) $user->id,
        'branch_id' => (int) $record['branches'][$branchIndex]->id,
    ]);

    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();

    return $user;
}

/**
 * @param  array{tenant: Tenant, branches: list<Branch>, user: User}  $record
 */
function ordersActionsTable(array $record, int $branchIndex, string $name): Table
{
    app(TenantResolver::class)->set((int) $record['tenant']->id);
    app(BranchContext::class)->set((int) $record['branches'][$branchIndex]->id);

    $hall = Hall::query()->create([
        'branch_id' => (int) $record['branches'][$branchIndex]->id,
        'translated_name' => ['hy' => "{$name} Hall", 'ru' => "{$name} Hall", 'en' => "{$name} Hall"],
        'color' => '#5FA8D3',
        'sort_order' => 10,
        'active' => true,
    ]);

    $table = Table::query()->create([
        'branch_id' => (int) $record['branches'][$branchIndex]->id,
        'hall_id' => (int) $hall->id,
        'translated_name' => ['hy' => $name, 'ru' => $name, 'en' => $name],
        'type' => 'standard',
        'shape' => 'square',
        'hdm_department' => 1,
        'is_delivery' => false,
        'sort_order' => 10,
        'active' => true,
    ]);

    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();

    return $table;
}

/**
 * @param  array{tenant: Tenant, branches: list<Branch>, user: User}  $record
 */
function ordersActionsActingIn(array $record, int $branchIndex, string $requestId): void
{
    app(TenantResolver::class)->set((int) $record['tenant']->id);
    app(BranchContext::class)->set((int) $record['branches'][$branchIndex]->id);
    auth()->login($record['user']);
    LogContext::start($requestId, 'orders');
}

function ordersActionsExpectMoveDomainCode(Closure $callback, string $errorCode): void
{
    Log::spy();

    try {
        $callback();
    } catch (OrdersDomainException $exception) {
        expect($exception->errorCode())->toBe($errorCode);

        Log::shouldHaveReceived('warning')
            ->with('action failed', Mockery::on(fn (array $context): bool => ($context['action'] ?? null) === 'orders.order.move'
                && ($context['error_code'] ?? null) === $errorCode))
            ->atLeast()
            ->once();

        return;
    }

    throw new RuntimeException("Expected {$errorCode}.");
}
