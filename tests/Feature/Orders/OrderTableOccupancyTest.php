<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Models\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Identity\Infrastructure\Models\UserBranchAssignment;
use App\Modules\Orders\Application\ListTableOccupancy;
use App\Modules\Orders\Application\TableOccupancy;
use App\Modules\Orders\Domain\OrdersDomainException;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Tables\Infrastructure\Models\Hall;
use App\Modules\Tables\Infrastructure\Models\Table;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Modules\Tenancy\Infrastructure\Models\Branch;
use App\Modules\Tenancy\Infrastructure\Models\Tenant;
use App\Support\Logging\LogContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

afterEach(function (): void {
    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();
    LogContext::clear();
});

it('lists complete dine-in table occupancy keyed by table id without pagination', function (): void {
    $record = ordersOccupancyUser('tenant-a', 'manager-a');

    ordersOccupancyActingIn($record, 0, 'orders-occupancy-list');

    $firstTable = ordersOccupancyTable($record, 0, 'Table 1');
    $firstOpenedAt = now()->subMinutes(30);
    $firstOrder = ordersOccupancyDineInOrder(
        $firstTable,
        $record['user'],
        openedAt: $firstOpenedAt,
        clientCount: 4,
        totalMinor: 12345,
        currency: 'USD',
    );

    for ($index = 2; $index <= 55; $index++) {
        $table = ordersOccupancyTable($record, 0, "Table {$index}");

        ordersOccupancyDineInOrder(
            $table,
            $record['user'],
            openedAt: now()->subMinutes($index),
            clientCount: $index,
            totalMinor: $index * 100,
        );
    }

    Log::spy();

    $occupancy = app(ListTableOccupancy::class)();
    $firstTableOccupancy = $occupancy[(int) $firstTable->id] ?? null;

    expect($occupancy)->toHaveCount(55)
        ->and(array_key_first($occupancy))->toBe((int) $firstTable->id)
        ->and($firstTableOccupancy)->toBeInstanceOf(TableOccupancy::class)
        ->and($firstTableOccupancy)->not->toBeInstanceOf(Model::class);

    assert($firstTableOccupancy instanceof TableOccupancy);

    expect($firstTableOccupancy->tableId)->toBe((int) $firstTable->id)
        ->and($firstTableOccupancy->orderId)->toBe((int) $firstOrder->id)
        ->and($firstTableOccupancy->openedAt->format('Y-m-d H:i:s'))->toBe($firstOpenedAt->format('Y-m-d H:i:s'))
        ->and($firstTableOccupancy->clientCount)->toBe(4)
        ->and($firstTableOccupancy->totalMinor)->toBe(12345)
        ->and($firstTableOccupancy->currency)->toBe('USD')
        ->and($firstTableOccupancy->waiterId)->toBe((int) $record['user']->id);

    Log::shouldHaveReceived('info')
        ->with('action performed', Mockery::on(fn (array $context): bool => ($context['action'] ?? null) === 'orders.occupancy.list'
            && ($context['branch_id'] ?? null) === (int) $record['branches'][0]->id
            && ($context['occupied_table_count'] ?? null) === 55))
        ->once();
});

it('excludes closed cancelled tableless and other branch orders', function (): void {
    $record = ordersOccupancyUser('tenant-a', 'manager-a', branchCount: 2);

    ordersOccupancyActingIn($record, 0, 'orders-occupancy-exclusions');

    $visibleTable = ordersOccupancyTable($record, 0, 'Visible Table');
    $visibleOrder = ordersOccupancyDineInOrder($visibleTable, $record['user']);
    ordersOccupancyDineInOrder(ordersOccupancyTable($record, 0, 'Cancelled Table'), $record['user'], status: 'cancelled');
    ordersOccupancyDineInOrder(ordersOccupancyTable($record, 0, 'Closed Table'), $record['user'], status: 'closed');
    ordersOccupancyTablelessOrder($record['branches'][0], $record['user'], type: 'fast_food');
    ordersOccupancyTablelessOrder($record['branches'][0], $record['user'], type: 'takeaway');
    ordersOccupancyTablelessOrder($record['branches'][0], $record['user'], type: 'delivery');

    ordersOccupancyActingIn($record, 1, 'orders-occupancy-other-branch');

    $otherBranchTable = ordersOccupancyTable($record, 1, 'Other Branch Table');
    ordersOccupancyDineInOrder($otherBranchTable, $record['user']);

    ordersOccupancyActingIn($record, 0, 'orders-occupancy-exclusions');

    $occupancy = app(ListTableOccupancy::class)();

    expect($occupancy)->toHaveCount(1)
        ->and(array_keys($occupancy))->toBe([(int) $visibleTable->id])
        ->and($occupancy[(int) $visibleTable->id]->orderId)->toBe((int) $visibleOrder->id);
});

it('keeps table occupancy tenant scoped even when another tenant branch id is requested', function (): void {
    $tenantA = ordersOccupancyUser('tenant-a', 'manager-a');
    $tenantB = ordersOccupancyUser('tenant-b', 'manager-b');

    ordersOccupancyActingIn($tenantA, 0, 'orders-occupancy-tenant-a');
    $tenantATable = ordersOccupancyTable($tenantA, 0, 'Tenant A Table');
    $tenantAOrder = ordersOccupancyDineInOrder($tenantATable, $tenantA['user']);

    ordersOccupancyActingIn($tenantB, 0, 'orders-occupancy-tenant-b');
    $tenantBTable = ordersOccupancyTable($tenantB, 0, 'Tenant B Table');
    $tenantBOrder = ordersOccupancyDineInOrder($tenantBTable, $tenantB['user']);

    app(TenantResolver::class)->set((int) $tenantA['tenant']->id);
    app(BranchContext::class)->set((int) $tenantB['branches'][0]->id);

    expect(app(ListTableOccupancy::class)())->toBe([]);

    ordersOccupancyActingIn($tenantA, 0, 'orders-occupancy-tenant-a');

    $tenantAOccupancy = app(ListTableOccupancy::class)();

    expect($tenantAOccupancy)->toHaveCount(1)
        ->and($tenantAOccupancy[(int) $tenantATable->id]->orderId)->toBe((int) $tenantAOrder->id);

    ordersOccupancyActingIn($tenantB, 0, 'orders-occupancy-tenant-b');

    $tenantBOccupancy = app(ListTableOccupancy::class)();

    expect($tenantBOccupancy)->toHaveCount(1)
        ->and($tenantBOccupancy[(int) $tenantBTable->id]->orderId)->toBe((int) $tenantBOrder->id);
});

it('requires a resolved branch context and logs the domain failure', function (): void {
    $record = ordersOccupancyUser('tenant-a', 'manager-a');

    app(TenantResolver::class)->set((int) $record['tenant']->id);
    app(BranchContext::class)->clear();
    LogContext::start('orders-occupancy-missing-branch', 'orders');
    Log::spy();

    try {
        app(ListTableOccupancy::class)();
    } catch (OrdersDomainException $exception) {
        expect($exception->errorCode())->toBe('orders.branch_context_required');

        Log::shouldHaveReceived('warning')
            ->with('action failed', Mockery::on(fn (array $context): bool => ($context['action'] ?? null) === 'orders.occupancy.list'
                && ($context['error_code'] ?? null) === 'orders.branch_context_required'))
            ->once();

        return;
    }

    throw new RuntimeException('Expected branch context guard to throw.');
});

it('executes one query without loading relations or causing n plus one reads', function (): void {
    $record = ordersOccupancyUser('tenant-a', 'manager-a');

    ordersOccupancyActingIn($record, 0, 'orders-occupancy-query-count');

    for ($index = 1; $index <= 4; $index++) {
        $table = ordersOccupancyTable($record, 0, "Query Count Table {$index}");

        ordersOccupancyDineInOrder(
            $table,
            $record['user'],
            openedAt: now()->subMinutes($index),
            clientCount: $index,
            totalMinor: $index * 1000,
        );
    }

    DB::flushQueryLog();
    DB::enableQueryLog();

    try {
        $occupancy = app(ListTableOccupancy::class)();

        foreach ($occupancy as $tableOccupancy) {
            $tableOccupancy->tableId;
            $tableOccupancy->orderId;
            $tableOccupancy->openedAt->format(DATE_ATOM);
            $tableOccupancy->clientCount;
            $tableOccupancy->totalMinor;
            $tableOccupancy->currency;
            $tableOccupancy->waiterId;
        }

        $queryCount = count(DB::getQueryLog());
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }

    expect($occupancy)->toHaveCount(4)
        ->and($queryCount)->toBe(1);
});

/**
 * @return array{tenant: Tenant, branches: list<Branch>, user: User}
 */
function ordersOccupancyUser(string $tenantSlug, string $username, int $branchCount = 1): array
{
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
function ordersOccupancyActingIn(array $record, int $branchIndex, string $requestId): void
{
    app(TenantResolver::class)->set((int) $record['tenant']->id);
    app(BranchContext::class)->set((int) $record['branches'][$branchIndex]->id);
    auth()->login($record['user']);
    LogContext::start($requestId, 'orders');
}

/**
 * @param  array{tenant: Tenant, branches: list<Branch>, user: User}  $record
 */
function ordersOccupancyTable(array $record, int $branchIndex, string $name): Table
{
    app(TenantResolver::class)->set((int) $record['tenant']->id);
    app(BranchContext::class)->set((int) $record['branches'][$branchIndex]->id);

    $hall = Hall::query()->create([
        'branch_id' => (int) $record['branches'][$branchIndex]->id,
        'translated_name' => ordersOccupancyTranslations("{$name} Hall"),
        'color' => '#5FA8D3',
        'sort_order' => 10,
        'active' => true,
    ]);

    return Table::query()->create([
        'branch_id' => (int) $record['branches'][$branchIndex]->id,
        'hall_id' => (int) $hall->id,
        'translated_name' => ordersOccupancyTranslations($name),
        'type' => 'standard',
        'shape' => 'square',
        'hdm_department' => 1,
        'is_delivery' => false,
        'sort_order' => 10,
        'active' => true,
    ]);
}

function ordersOccupancyDineInOrder(
    Table $table,
    ?User $waiter,
    string $status = 'open',
    ?DateTimeInterface $openedAt = null,
    int $clientCount = 1,
    int $totalMinor = 0,
    string $currency = 'AMD',
): Order {
    return Order::query()->create([
        'branch_id' => (int) $table->branch_id,
        'type' => 'dine_in',
        'status' => $status,
        'table_id' => (int) $table->id,
        'waiter_id' => $waiter instanceof User ? (int) $waiter->id : null,
        'opened_at' => $openedAt ?? now(),
        'closed_at' => $status === 'open' ? null : now(),
        'client_count' => $clientCount,
        'subtotal_minor' => $totalMinor,
        'discount_minor' => 0,
        'total_minor' => $totalMinor,
        'currency' => $currency,
    ]);
}

function ordersOccupancyTablelessOrder(Branch $branch, ?User $waiter, string $type): Order
{
    return Order::query()->create([
        'branch_id' => (int) $branch->id,
        'type' => $type,
        'status' => 'open',
        'table_id' => null,
        'waiter_id' => $waiter instanceof User ? (int) $waiter->id : null,
        'opened_at' => now(),
        'closed_at' => null,
        'client_count' => 1,
        'subtotal_minor' => 0,
        'discount_minor' => 0,
        'total_minor' => 0,
        'currency' => 'AMD',
    ]);
}

/**
 * @return array{hy: string, ru: string, en: string}
 */
function ordersOccupancyTranslations(string $text): array
{
    return [
        'hy' => $text,
        'ru' => $text,
        'en' => $text,
    ];
}
