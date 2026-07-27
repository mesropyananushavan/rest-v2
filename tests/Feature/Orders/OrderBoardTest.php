<?php

declare(strict_types=1);

use App\Livewire\Admin\OrderBoard;
use App\Modules\Identity\Infrastructure\Models\Permission;
use App\Modules\Identity\Infrastructure\Models\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Identity\Infrastructure\Models\UserBranchAssignment;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Tables\Infrastructure\Models\Hall;
use App\Modules\Tables\Infrastructure\Models\Table;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Modules\Tenancy\Infrastructure\Models\Branch;
use App\Modules\Tenancy\Infrastructure\Models\Tenant;
use App\Support\Logging\LogContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

afterEach(function (): void {
    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();
    LogContext::clear();
});

it('requires authentication and the orders take permission for the board route', function (): void {
    $allowed = orderBoardUser('tenant-a', 'waiter-a', ['orders.take']);
    $denied = orderBoardUser('tenant-b', 'viewer-b', []);

    $this->get(route('admin.orders.board'))
        ->assertRedirect(route('login'));

    $this->actingAs($denied['user'])
        ->withSession(['branch_id' => (int) $denied['branches'][0]->id])
        ->get(route('admin.orders.board'))
        ->assertForbidden();

    $this->actingAs($allowed['user'])
        ->withSession(['branch_id' => (int) $allowed['branches'][0]->id])
        ->get(route('admin.orders.board'))
        ->assertOk()
        ->assertSeeLivewire(OrderBoard::class)
        ->assertSee(__('admin.nav.orders_board'), false)
        ->assertSee(__('orders.board.heading'), false);
});

it('renders occupied and free active branch tables as read only tiles', function (): void {
    $record = orderBoardUser('tenant-a', 'waiter-a', ['orders.take']);

    orderBoardContext($record, 0);

    $hall = orderBoardHall($record['branches'][0], 'Main Hall', sortOrder: 10);
    $occupiedTable = orderBoardTable($hall, 'Window 1', sortOrder: 10);
    $freeTable = orderBoardTable($hall, 'Window 2', sortOrder: 20, shape: 'circle');
    $order = orderBoardDineInOrder($occupiedTable, $record['user'], clientCount: 4, totalMinor: 12300);

    Livewire::actingAs($record['user'])
        ->test(OrderBoard::class)
        ->assertSee('Main Hall', false)
        ->assertSee('Window 1', false)
        ->assertSee('Window 2', false)
        ->assertSee(__('orders.board.occupied'), false)
        ->assertSee(__('orders.board.free'), false)
        ->assertSee(__('orders.board.order_number', ['id' => (int) $order->id]), false)
        ->assertSee(__('orders.board.guests', ['count' => 4]), false)
        ->assertSee(__('orders.board.total'), false)
        ->assertSee('123 ֏', false)
        ->assertDontSee('wire:click', false);

    expect($freeTable)->toBeInstanceOf(Table::class);
});

it('shows only the active branch layout and occupancy', function (): void {
    $tenantA = orderBoardUser('tenant-a', 'waiter-a', ['orders.take'], branchCount: 2);
    $tenantB = orderBoardUser('tenant-b', 'waiter-b', ['orders.take']);

    orderBoardContext($tenantA, 0);
    $visibleHall = orderBoardHall($tenantA['branches'][0], 'Tenant A Branch 1 Hall');
    $visibleTable = orderBoardTable($visibleHall, 'Tenant A Branch 1 Table');
    orderBoardDineInOrder($visibleTable, $tenantA['user']);

    orderBoardContext($tenantA, 1);
    $otherBranchHall = orderBoardHall($tenantA['branches'][1], 'Tenant A Branch 2 Hall');
    $otherBranchTable = orderBoardTable($otherBranchHall, 'Tenant A Branch 2 Table');
    orderBoardDineInOrder($otherBranchTable, $tenantA['user']);

    orderBoardContext($tenantB, 0);
    $otherTenantHall = orderBoardHall($tenantB['branches'][0], 'Tenant B Hall');
    $otherTenantTable = orderBoardTable($otherTenantHall, 'Tenant B Table');
    orderBoardDineInOrder($otherTenantTable, $tenantB['user']);

    orderBoardContext($tenantA, 0);

    Livewire::actingAs($tenantA['user'])
        ->test(OrderBoard::class)
        ->assertSee('Tenant A Branch 1 Hall', false)
        ->assertSee('Tenant A Branch 1 Table', false)
        ->assertDontSee('Tenant A Branch 2 Hall', false)
        ->assertDontSee('Tenant A Branch 2 Table', false)
        ->assertDontSee('Tenant B Hall', false)
        ->assertDontSee('Tenant B Table', false);
});

it('renders the localized empty board state', function (): void {
    $record = orderBoardUser('tenant-a', 'waiter-a', ['orders.take']);

    orderBoardContext($record, 0);

    Livewire::actingAs($record['user'])
        ->test(OrderBoard::class)
        ->assertSee(__('orders.board.empty_title'), false)
        ->assertSee(__('orders.board.empty_body'), false);
});

/**
 * @param  list<string>  $permissionCodes
 * @return array{tenant: Tenant, branches: list<Branch>, user: User}
 */
function orderBoardUser(string $tenantSlug, string $username, array $permissionCodes, int $branchCount = 1): array
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

    $permissions = collect($permissionCodes)
        ->map(fn (string $code): Permission => Permission::query()->create([
            'code' => $code,
            'name' => $code,
        ]));

    if ($permissions->isNotEmpty()) {
        $role->permissions()->attach(
            $permissions->pluck('id')->all(),
            ['tenant_id' => (int) $tenant->id],
        );
    }

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
function orderBoardContext(array $record, int $branchIndex): void
{
    app(TenantResolver::class)->set((int) $record['tenant']->id);
    app(BranchContext::class)->set((int) $record['branches'][$branchIndex]->id);
    auth()->login($record['user']);
    LogContext::start('order-board-context', 'orders');
}

function orderBoardHall(Branch $branch, string $name, int $sortOrder = 0, string $color = '#5FA8D3', bool $active = true): Hall
{
    return Hall::query()->create([
        'branch_id' => (int) $branch->id,
        'translated_name' => orderBoardText($name),
        'color' => $color,
        'sort_order' => $sortOrder,
        'active' => $active,
    ]);
}

function orderBoardTable(Hall $hall, string $name, int $sortOrder = 0, string $type = 'standard', string $shape = 'square', bool $active = true): Table
{
    return Table::query()->create([
        'branch_id' => (int) $hall->branch_id,
        'hall_id' => (int) $hall->id,
        'translated_name' => orderBoardText($name),
        'type' => $type,
        'shape' => $shape,
        'hdm_department' => null,
        'is_delivery' => false,
        'sort_order' => $sortOrder,
        'active' => $active,
    ]);
}

function orderBoardDineInOrder(Table $table, User $user, int $clientCount = 2, int $totalMinor = 0): Order
{
    return Order::query()->create([
        'branch_id' => (int) $table->branch_id,
        'type' => 'dine_in',
        'status' => 'open',
        'table_id' => (int) $table->id,
        'waiter_id' => (int) $user->id,
        'opened_at' => now()->subMinutes(35),
        'client_count' => $clientCount,
        'comment' => null,
        'subtotal_minor' => $totalMinor,
        'discount_minor' => 0,
        'total_minor' => $totalMinor,
        'currency' => 'AMD',
    ]);
}

/**
 * @return array{hy: string, ru: string, en: string}
 */
function orderBoardText(string $text): array
{
    return [
        'hy' => $text,
        'ru' => $text,
        'en' => $text,
    ];
}
