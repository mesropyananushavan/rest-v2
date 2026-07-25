<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Models\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Identity\Infrastructure\Models\UserBranchAssignment;
use App\Modules\Orders\Application\AddSubtable;
use App\Modules\Orders\Application\AssignWaiter;
use App\Modules\Orders\Application\CancelOrder;
use App\Modules\Orders\Application\FindOrder;
use App\Modules\Orders\Application\ListOpenOrders;
use App\Modules\Orders\Application\OpenOrder;
use App\Modules\Orders\Domain\OrdersDomainException;
use App\Modules\Orders\Infrastructure\Models\Order;
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

    $assigned = app(AssignWaiter::class)((int) $order->id, null);
    $subtable = app(AddSubtable::class)((int) $order->id, 'Guest 1');
    $found = app(FindOrder::class)((int) $order->id);
    $openOrders = app(ListOpenOrders::class)(perPage: 25);

    expect($assigned->waiter_id)->toBeNull()
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
        'orders.order.cancelled',
        'orders.order.opened',
    ])->and(AuditLog::query()->where('target_type', 'orders_subtable')->pluck('action')->all())->toBe([
        'orders.subtable.added',
    ]);
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
