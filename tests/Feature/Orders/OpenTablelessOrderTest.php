<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Models\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Identity\Infrastructure\Models\UserBranchAssignment;
use App\Modules\Menu\Infrastructure\Models\MenuCategory;
use App\Modules\Menu\Infrastructure\Models\MenuItem;
use App\Modules\Orders\Application\AddItem;
use App\Modules\Orders\Application\OpenTablelessOrder;
use App\Modules\Orders\Domain\OrdersDomainException;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Orders\Infrastructure\Models\OrderItem;
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

it('opens every tableless order type with null table zero totals and audit rows', function (): void {
    $record = tablelessOrdersUser('tenant-a', 'manager-a', currency: 'USD');

    tablelessOrdersActingIn($record, 'orders-tableless-open');

    foreach (['fast_food', 'takeaway', 'delivery'] as $type) {
        $order = app(OpenTablelessOrder::class)(
            type: $type,
            clientCount: 0,
            comment: "{$type} comment",
            customerId: 123,
        );

        expect((int) $order->branch_id)->toBe((int) $record['branch']->id)
            ->and($order->type)->toBe($type)
            ->and($order->status)->toBe('open')
            ->and($order->table_id)->toBeNull()
            ->and($order->customer_id)->toBe(123)
            ->and((int) $order->waiter_id)->toBe((int) $record['user']->id)
            ->and($order->cashier_id)->toBeNull()
            ->and($order->closed_at)->toBeNull()
            ->and($order->client_count)->toBe(1)
            ->and($order->comment)->toBe("{$type} comment")
            ->and($order->subtotal()->minor)->toBe(0)
            ->and($order->discount()->minor)->toBe(0)
            ->and($order->total()->minor)->toBe(0)
            ->and($order->total()->currency)->toBe('USD');
    }

    $secondFastFood = app(OpenTablelessOrder::class)('fast_food', waiterId: null);

    expect(Order::query()->where('type', 'fast_food')->where('status', 'open')->count())->toBe(2)
        ->and($secondFastFood->table_id)->toBeNull();

    $audits = AuditLog::query()
        ->where('target_type', 'orders_order')
        ->where('action', 'orders.order.opened')
        ->orderBy('id')
        ->get();

    expect($audits)->toHaveCount(4)
        ->and($audits->pluck('after_json')->map(fn (array $payload): mixed => $payload['table_id'])->all())->toBe([null, null, null, null])
        ->and($audits->pluck('after_json')->map(fn (array $payload): mixed => $payload['type'])->all())->toBe([
            'fast_food',
            'takeaway',
            'delivery',
            'fast_food',
        ]);
});

it('rejects dine-in and unknown tableless types before writing an order', function (): void {
    $record = tablelessOrdersUser('tenant-a', 'manager-a');

    tablelessOrdersActingIn($record, 'orders-tableless-invalid-type');

    foreach (['dine_in', 'unknown'] as $type) {
        tablelessOrdersExpectDomainCode(
            fn () => app(OpenTablelessOrder::class)($type),
            'orders.invalid_order_type',
        );
    }

    expect(Order::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0);
});

it('requires tenant and branch context for tableless orders', function (): void {
    $record = tablelessOrdersUser('tenant-a', 'manager-a');

    tablelessOrdersExpectDomainCode(
        fn () => app(OpenTablelessOrder::class)('fast_food'),
        'orders.tenant_context_required',
    );

    app(TenantResolver::class)->set((int) $record['tenant']->id);

    tablelessOrdersExpectDomainCode(
        fn () => app(OpenTablelessOrder::class)('takeaway'),
        'orders.branch_context_required',
    );
});

it('allows existing item mutations on tableless orders and recomputes totals', function (): void {
    $record = tablelessOrdersUser('tenant-a', 'manager-a');
    $dolma = tablelessOrdersMenuItem($record, 'Dolma', 1200);

    tablelessOrdersActingIn($record, 'orders-tableless-items');

    $order = app(OpenTablelessOrder::class)('fast_food');
    $line = app(AddItem::class)((int) $order->id, (int) $dolma->id, 2);
    $freshOrder = Order::query()->findOrFail((int) $order->id);

    expect($order->table_id)->toBeNull()
        ->and((int) $line->order_id)->toBe((int) $order->id)
        ->and((int) $line->qty)->toBe(2)
        ->and((int) $line->unit_price_minor)->toBe(1200)
        ->and((int) $line->total_minor)->toBe(2400)
        ->and((int) $freshOrder->subtotal_minor)->toBe(2400)
        ->and((int) $freshOrder->total_minor)->toBe(2400)
        ->and((int) OrderItem::query()->where('order_id', (int) $order->id)->sum('total_minor'))->toBe(2400);
});

/**
 * @return array{tenant: Tenant, branch: Branch, user: User}
 */
function tablelessOrdersUser(string $tenantSlug, string $username, string $currency = 'AMD'): array
{
    $tenant = Tenant::query()->create([
        'name' => str($tenantSlug)->headline()->toString(),
        'slug' => $tenantSlug,
        'default_locale' => 'en',
        'currency' => $currency,
        'status' => 'active',
    ]);

    app(TenantResolver::class)->set((int) $tenant->id);

    $branch = Branch::query()->create([
        'name' => "{$tenantSlug} Branch",
        'timezone' => 'Asia/Yerevan',
        'status' => 'active',
    ]);

    app(BranchContext::class)->set((int) $branch->id);

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

    UserBranchAssignment::query()->create([
        'user_id' => (int) $user->id,
        'branch_id' => (int) $branch->id,
    ]);

    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();

    return [
        'tenant' => $tenant,
        'branch' => $branch,
        'user' => $user,
    ];
}

/**
 * @param  array{tenant: Tenant, branch: Branch, user: User}  $record
 */
function tablelessOrdersMenuItem(array $record, string $name, int $priceMinor): MenuItem
{
    app(TenantResolver::class)->set((int) $record['tenant']->id);
    app(BranchContext::class)->set((int) $record['branch']->id);

    $root = MenuCategory::query()->create([
        'translated_name' => ['hy' => "{$name} Root", 'ru' => "{$name} Root", 'en' => "{$name} Root"],
        'sort_order' => 0,
        'active' => true,
    ]);

    $category = MenuCategory::query()->create([
        'parent_id' => (int) $root->id,
        'translated_name' => ['hy' => "{$name} Category", 'ru' => "{$name} Category", 'en' => "{$name} Category"],
        'sort_order' => 10,
        'active' => true,
    ]);

    $item = MenuItem::query()->create([
        'branch_id' => (int) $record['branch']->id,
        'category_id' => (int) $category->id,
        'translated_name' => ['hy' => $name, 'ru' => $name, 'en' => $name],
        'translated_description' => ['hy' => "{$name} Description", 'ru' => "{$name} Description", 'en' => "{$name} Description"],
        'price_minor' => $priceMinor,
        'currency' => (string) $record['tenant']->currency,
        'active' => true,
    ]);

    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();

    return $item;
}

/**
 * @param  array{tenant: Tenant, branch: Branch, user: User}  $record
 */
function tablelessOrdersActingIn(array $record, string $requestId): void
{
    app(TenantResolver::class)->set((int) $record['tenant']->id);
    app(BranchContext::class)->set((int) $record['branch']->id);
    auth()->login($record['user']);
    LogContext::start($requestId, 'orders');
}

function tablelessOrdersExpectDomainCode(Closure $callback, string $errorCode): void
{
    try {
        $callback();
    } catch (OrdersDomainException $exception) {
        expect($exception->errorCode())->toBe($errorCode);

        return;
    }

    throw new RuntimeException("Expected {$errorCode}.");
}
