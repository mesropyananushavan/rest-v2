<?php

declare(strict_types=1);

use App\Livewire\Admin\OrderWorkspace as OrderWorkspaceComponent;
use App\Modules\Identity\Infrastructure\Models\Permission;
use App\Modules\Identity\Infrastructure\Models\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Identity\Infrastructure\Models\UserBranchAssignment;
use App\Modules\Orders\Contracts\OrderPermissions;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Payments\Contracts\PaymentPermissions;
use App\Modules\Payments\Infrastructure\Models\Cashbox;
use App\Modules\Payments\Infrastructure\Models\CashboxEntry;
use App\Modules\Payments\Infrastructure\Models\Payment;
use App\Modules\Payments\Infrastructure\Models\PaymentAllocation;
use App\Modules\Tables\Infrastructure\Models\Hall;
use App\Modules\Tables\Infrastructure\Models\Table;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Modules\Tenancy\Infrastructure\Models\Branch;
use App\Modules\Tenancy\Infrastructure\Models\Tenant;
use App\Support\Audit\AuditLog;
use App\Support\Logging\LogContext;
use App\Support\Money\Money;
use App\Support\Money\MoneyFormatter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

afterEach(function (): void {
    auth()->logout();
    app()->setLocale('hy');
    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();
    LogContext::clear();
});

it('captures the full outstanding cash amount from the order workspace', function (): void {
    $record = orderWorkspacePaymentFixture('capture-success');

    app()->setLocale('en');
    orderWorkspacePaymentActingIn($record, 'workspace-payment-success');

    $component = Livewire::actingAs($record['user'])
        ->test(OrderWorkspaceComponent::class, ['orderId' => $record['order_id']])
        ->assertSee(__('payments.workspace.title'), false)
        ->assertSee(__('payments.workspace.actions.capture_full_cash'), false)
        ->assertSee(orderWorkspacePaymentMoney(6_500), false)
        ->assertSee($record['cashboxes'][0]->name, false)
        ->assertSet('selectedCashboxId', (string) $record['cashboxes'][0]->id)
        ->assertSet('cashPaymentExpectedAmountMinor', 6_500)
        ->assertSet('cashPaymentExpectedCurrency', 'AMD');

    assertRenderedHtmlHasNoUncompiledBladeDirectiveAttributes($component->html());
    assertRenderedLivewireBindingsResolve($component->html(), OrderWorkspaceComponent::class);

    $component
        ->call('captureFullCashPayment')
        ->assertSet('statusMessage', __('payments.workspace.flash.captured', [
            'amount' => orderWorkspacePaymentMoney(6_500),
        ]))
        ->assertSee(__('payments.order_already_fully_paid'), false);

    $payment = Payment::query()->sole();
    $allocation = PaymentAllocation::query()->sole();
    $entry = CashboxEntry::query()->sole();
    $audit = AuditLog::query()->where('action', 'payments.payment.captured')->sole();
    $order = Order::query()->findOrFail($record['order_id']);

    expect((int) $payment->tenant_id)->toBe($record['tenant_id'])
        ->and((int) $payment->branch_id)->toBe($record['branch_id'])
        ->and((int) $payment->order_id)->toBe($record['order_id'])
        ->and((int) $payment->cashbox_id)->toBe((int) $record['cashboxes'][0]->id)
        ->and($payment->method)->toBe('cash')
        ->and($payment->status)->toBe('captured')
        ->and($payment->amount_minor)->toBe(6_500)
        ->and($payment->currency)->toBe('AMD')
        ->and((string) $payment->idempotency_key)->toStartWith('order-workspace:')
        ->and((int) $allocation->payment_id)->toBe((int) $payment->id)
        ->and($allocation->payable_type)->toBe('order')
        ->and((int) $allocation->payable_id)->toBe($record['order_id'])
        ->and($allocation->amount_minor)->toBe(6_500)
        ->and((int) $entry->cashbox_id)->toBe((int) $record['cashboxes'][0]->id)
        ->and($entry->direction)->toBe('in')
        ->and($entry->reason)->toBe('cash_payment')
        ->and((int) $entry->source_id)->toBe((int) $payment->id)
        ->and((int) $entry->posted_by_id)->toBe($record['user_id'])
        ->and($audit->target_type)->toBe('payments_payment')
        ->and($audit->target_id)->toBe((int) $payment->id)
        ->and($audit->actor_id)->toBe($record['user_id'])
        ->and((string) $order->status)->toBe('open')
        ->and($order->closed_at)->toBeNull()
        ->and((int) $order->total_minor)->toBe(6_500);
});

it('hides the workspace payment adapter and forbids direct calls without payments capture permission', function (): void {
    $record = orderWorkspacePaymentFixture('payment-denied', [OrderPermissions::TAKE]);

    app()->setLocale('en');
    orderWorkspacePaymentActingIn($record, 'workspace-payment-denied');

    Livewire::actingAs($record['user'])
        ->test(OrderWorkspaceComponent::class, ['orderId' => $record['order_id']])
        ->assertDontSee(__('payments.workspace.actions.capture_full_cash'), false)
        ->call('captureFullCashPayment')
        ->assertStatus(403);

    orderWorkspacePaymentAssertNoFinancialFacts($record);
});

it('requires explicit cashbox selection when more than one active cashbox is available', function (): void {
    $record = orderWorkspacePaymentFixture('multi-cashbox', cashboxCount: 2);
    $secondCashbox = $record['cashboxes'][1];

    app()->setLocale('en');
    orderWorkspacePaymentActingIn($record, 'workspace-payment-multi-cashbox');

    $component = Livewire::actingAs($record['user'])
        ->test(OrderWorkspaceComponent::class, ['orderId' => $record['order_id']])
        ->assertSet('selectedCashboxId', '')
        ->assertSee(__('payments.workspace.cashbox_placeholder'), false)
        ->call('captureFullCashPayment')
        ->assertSet('errorMessage', __('payments.workspace.validation.cashbox_required'));

    orderWorkspacePaymentAssertNoFinancialFacts($record);

    $component
        ->set('selectedCashboxId', (string) $secondCashbox->id)
        ->call('captureFullCashPayment')
        ->assertSet('statusMessage', __('payments.workspace.flash.captured', [
            'amount' => orderWorkspacePaymentMoney(6_500),
        ]));

    expect((int) Payment::query()->sole()->cashbox_id)->toBe((int) $secondCashbox->id);
});

it('rejects foreign and inactive cashboxes from the workspace adapter', function (): void {
    $record = orderWorkspacePaymentFixture('cashbox-scope');
    $foreign = orderWorkspacePaymentFixture('cashbox-foreign');

    app()->setLocale('en');
    orderWorkspacePaymentActingIn($record, 'workspace-payment-foreign-cashbox');

    $component = Livewire::actingAs($record['user'])
        ->test(OrderWorkspaceComponent::class, ['orderId' => $record['order_id']]);
    $component->instance()->selectedCashboxId = (string) $foreign['cashboxes'][0]->id;
    $component->instance()->captureFullCashPayment();

    expect($component->instance()->errorMessage)->toBe(__('payments.workspace.errors.cashbox_unavailable'));

    orderWorkspacePaymentAssertNoFinancialFacts($record);

    $inactive = orderWorkspacePaymentFixture('cashbox-inactive', cashboxActive: false);

    orderWorkspacePaymentActingIn($inactive, 'workspace-payment-inactive-cashbox');

    $inactiveComponent = Livewire::actingAs($inactive['user'])
        ->test(OrderWorkspaceComponent::class, ['orderId' => $inactive['order_id']])
        ->assertSee(__('payments.workspace.unavailable.no_cashboxes'), false);
    $inactiveComponent->instance()->selectedCashboxId = (string) $inactive['cashboxes'][0]->id;
    $inactiveComponent->instance()->captureFullCashPayment();

    expect($inactiveComponent->instance()->errorMessage)->toBe(__('payments.cashbox_unavailable'));

    orderWorkspacePaymentAssertNoFinancialFacts($inactive);
});

it('does not expose cross tenant orders through the payment adapter', function (): void {
    $record = orderWorkspacePaymentFixture('tenant-order-a');
    $foreign = orderWorkspacePaymentFixture('tenant-order-b');

    orderWorkspacePaymentActingIn($record, 'workspace-payment-foreign-order');

    $this->actingAs($record['user'])
        ->withSession(['branch_id' => $record['branch_id']])
        ->get(route('admin.orders.workspace', ['order' => $foreign['order_id']]))
        ->assertNotFound();

    orderWorkspacePaymentAssertNoFinancialFacts($foreign);
});

it('blocks zero outstanding orders and repeated new capture attempts after full payment', function (): void {
    $zero = orderWorkspacePaymentFixture('zero-total', totalMinor: 0);

    app()->setLocale('en');
    orderWorkspacePaymentActingIn($zero, 'workspace-payment-zero');

    Livewire::actingAs($zero['user'])
        ->test(OrderWorkspaceComponent::class, ['orderId' => $zero['order_id']])
        ->assertSee(__('orders.order_not_payable'), false)
        ->set('selectedCashboxId', (string) $zero['cashboxes'][0]->id)
        ->call('captureFullCashPayment')
        ->assertSet('errorMessage', __('orders.order_not_payable'));

    orderWorkspacePaymentAssertNoFinancialFacts($zero);

    $paid = orderWorkspacePaymentFixture('already-paid');
    orderWorkspacePaymentActingIn($paid, 'workspace-payment-already-paid');

    Livewire::actingAs($paid['user'])
        ->test(OrderWorkspaceComponent::class, ['orderId' => $paid['order_id']])
        ->call('captureFullCashPayment')
        ->set('cashPaymentIdempotencyKey', 'order-workspace:new-paid-key')
        ->set('selectedCashboxId', (string) $paid['cashboxes'][0]->id)
        ->call('captureFullCashPayment')
        ->assertSet('errorMessage', __('payments.order_already_fully_paid'));

    orderWorkspacePaymentAssertFinancialCounts($paid, 1);
});

it('keeps identical workspace resubmission idempotent and detects conflicting key reuse', function (): void {
    $record = orderWorkspacePaymentFixture('idempotent-replay');

    app()->setLocale('en');
    orderWorkspacePaymentActingIn($record, 'workspace-payment-replay');

    $component = Livewire::actingAs($record['user'])
        ->test(OrderWorkspaceComponent::class, ['orderId' => $record['order_id']])
        ->set('cashPaymentIdempotencyKey', 'order-workspace:stable-replay-key')
        ->set('cashPaymentExpectedAmountMinor', 6_500)
        ->set('cashPaymentExpectedCurrency', 'AMD')
        ->call('captureFullCashPayment')
        ->assertSet('statusMessage', __('payments.workspace.flash.captured', [
            'amount' => orderWorkspacePaymentMoney(6_500),
        ]));

    $component
        ->set('cashPaymentIdempotencyKey', 'order-workspace:stable-replay-key')
        ->set('cashPaymentExpectedAmountMinor', 6_500)
        ->set('cashPaymentExpectedCurrency', 'AMD')
        ->set('selectedCashboxId', (string) $record['cashboxes'][0]->id)
        ->call('captureFullCashPayment')
        ->assertSet('statusMessage', __('payments.workspace.flash.captured', [
            'amount' => orderWorkspacePaymentMoney(6_500),
        ]));

    orderWorkspacePaymentAssertFinancialCounts($record, 1);

    $secondOrder = orderWorkspacePaymentOrder($record['branch'], $record['user'], 'conflicting-key-order');

    Livewire::actingAs($record['user'])
        ->test(OrderWorkspaceComponent::class, ['orderId' => (int) $secondOrder->id])
        ->set('cashPaymentIdempotencyKey', 'order-workspace:stable-replay-key')
        ->call('captureFullCashPayment')
        ->assertSet('errorMessage', __('payments.idempotency_conflict'));

    orderWorkspacePaymentAssertFinancialCounts($record, 1);
});

it('handles stale payable amounts safely without appending financial rows', function (): void {
    $record = orderWorkspacePaymentFixture('stale-amount');

    app()->setLocale('en');
    orderWorkspacePaymentActingIn($record, 'workspace-payment-stale');

    $component = Livewire::actingAs($record['user'])
        ->test(OrderWorkspaceComponent::class, ['orderId' => $record['order_id']])
        ->assertSet('cashPaymentExpectedAmountMinor', 6_500);

    DB::table('orders')
        ->where('id', $record['order_id'])
        ->update([
            'subtotal_minor' => 7_000,
            'total_minor' => 7_000,
            'updated_at' => now(),
        ]);

    $component
        ->call('captureFullCashPayment')
        ->assertSet('errorMessage', __('payments.workspace.errors.stale_amount'));

    orderWorkspacePaymentAssertNoFinancialFacts($record);
});

it('keeps payment translation key sets identical across supported locales', function (): void {
    $english = orderWorkspacePaymentFlattenKeys(require lang_path('en/payments.php'));
    $armenian = orderWorkspacePaymentFlattenKeys(require lang_path('hy/payments.php'));
    $russian = orderWorkspacePaymentFlattenKeys(require lang_path('ru/payments.php'));

    expect($armenian)->toBe($english)
        ->and($russian)->toBe($english);
});

/**
 * @param  list<string>  $permissions
 * @return array{tenant: Tenant, tenant_id: int, branch: Branch, branch_id: int, user: User, user_id: int, order: Order, order_id: int, cashboxes: list<Cashbox>}
 */
function orderWorkspacePaymentFixture(
    string $suffix,
    array $permissions = [OrderPermissions::TAKE, PaymentPermissions::CAPTURE],
    int $totalMinor = 6_500,
    bool $cashboxActive = true,
    int $cashboxCount = 1,
): array {
    $tenant = Tenant::query()->create([
        'name' => "Workspace Payment {$suffix}",
        'slug' => "workspace-payment-{$suffix}-".str()->random(6),
        'default_locale' => 'en',
        'currency' => 'AMD',
        'status' => 'active',
    ]);

    app(TenantResolver::class)->set((int) $tenant->id);

    $branch = Branch::query()->create([
        'name' => "Workspace Payment {$suffix} Branch",
        'timezone' => 'Asia/Yerevan',
        'status' => 'active',
    ]);

    app(BranchContext::class)->set((int) $branch->id);

    $role = Role::query()->create([
        'code' => "workspace-payment-{$suffix}",
        'name' => "Workspace Payment {$suffix}",
        'is_management_role' => false,
    ]);

    foreach ($permissions as $permissionCode) {
        $permission = Permission::query()->firstOrCreate(
            ['code' => $permissionCode],
            ['name' => $permissionCode],
        );

        $role->permissions()->attach((int) $permission->id, ['tenant_id' => (int) $tenant->id]);
    }

    $user = User::query()->create([
        'role_id' => (int) $role->id,
        'name' => "Workspace Payment {$suffix} Cashier",
        'email' => "workspace-payment-{$suffix}-".str()->random(6).'@smartrest.test',
        'username' => "workspace-payment-{$suffix}-".str()->random(6),
        'default_locale' => 'en',
        'active' => true,
        'is_superadmin' => false,
        'password' => Hash::make('password'),
    ]);

    UserBranchAssignment::query()->create([
        'user_id' => (int) $user->id,
        'branch_id' => (int) $branch->id,
    ]);

    $order = orderWorkspacePaymentOrder($branch, $user, $suffix, totalMinor: $totalMinor);
    $cashboxes = [];

    for ($index = 1; $index <= $cashboxCount; $index++) {
        $cashboxes[] = Cashbox::query()->create([
            'branch_id' => (int) $branch->id,
            'name' => "Register {$suffix} {$index}",
            'is_active' => $cashboxActive,
            'is_default' => $cashboxActive && $index === 1,
        ]);
    }

    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();

    return [
        'tenant' => $tenant,
        'tenant_id' => (int) $tenant->id,
        'branch' => $branch,
        'branch_id' => (int) $branch->id,
        'user' => $user,
        'user_id' => (int) $user->id,
        'order' => $order,
        'order_id' => (int) $order->id,
        'cashboxes' => $cashboxes,
    ];
}

function orderWorkspacePaymentOrder(Branch $branch, User $waiter, string $suffix, int $totalMinor = 6_500): Order
{
    app(TenantResolver::class)->set((int) $branch->tenant_id);
    app(BranchContext::class)->set((int) $branch->id);

    $hall = Hall::query()->create([
        'branch_id' => (int) $branch->id,
        'translated_name' => orderWorkspacePaymentTranslations("Hall {$suffix}"),
        'color' => '#5FA8D3',
        'sort_order' => 10,
        'active' => true,
    ]);

    $table = Table::query()->create([
        'branch_id' => (int) $branch->id,
        'hall_id' => (int) $hall->id,
        'translated_name' => orderWorkspacePaymentTranslations("Table {$suffix}"),
        'type' => 'standard',
        'shape' => 'square',
        'hdm_department' => 1,
        'is_delivery' => false,
        'sort_order' => 10,
        'active' => true,
    ]);

    return Order::query()->create([
        'branch_id' => (int) $branch->id,
        'type' => 'dine_in',
        'status' => 'open',
        'table_id' => (int) $table->id,
        'waiter_id' => (int) $waiter->id,
        'opened_at' => now(),
        'closed_at' => null,
        'client_count' => 1,
        'comment' => "Workspace payment {$suffix}",
        'subtotal_minor' => $totalMinor,
        'discount_minor' => 0,
        'total_minor' => $totalMinor,
        'currency' => 'AMD',
    ]);
}

/**
 * @param  array{tenant_id: int, branch_id: int, user: User}  $record
 */
function orderWorkspacePaymentActingIn(array $record, string $requestId): void
{
    app(TenantResolver::class)->set($record['tenant_id']);
    app(BranchContext::class)->set($record['branch_id']);
    auth()->login($record['user']);
    LogContext::start($requestId, 'orders');
}

/**
 * @return array{hy: string, ru: string, en: string}
 */
function orderWorkspacePaymentTranslations(string $text): array
{
    return [
        'hy' => $text,
        'ru' => $text,
        'en' => $text,
    ];
}

function orderWorkspacePaymentMoney(int $minor): string
{
    return MoneyFormatter::format(new Money($minor, 'AMD'), 'en');
}

/**
 * @param  array<string, mixed>  $translations
 * @return list<string>
 */
function orderWorkspacePaymentFlattenKeys(array $translations, string $prefix = ''): array
{
    $keys = [];

    foreach ($translations as $key => $value) {
        $fullKey = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

        if (is_array($value)) {
            array_push($keys, ...orderWorkspacePaymentFlattenKeys($value, $fullKey));

            continue;
        }

        $keys[] = $fullKey;
    }

    sort($keys);

    return $keys;
}

/**
 * @param  array{tenant_id: int, order_id: int}  $record
 */
function orderWorkspacePaymentAssertNoFinancialFacts(array $record): void
{
    orderWorkspacePaymentAssertFinancialCounts($record, 0);
}

/**
 * @param  array{tenant_id: int, order_id: int}  $record
 */
function orderWorkspacePaymentAssertFinancialCounts(array $record, int $expected): void
{
    app(TenantResolver::class)->set($record['tenant_id']);

    expect(Payment::query()->where('order_id', $record['order_id'])->count())->toBe($expected)
        ->and(PaymentAllocation::query()->where('payable_type', 'order')->where('payable_id', $record['order_id'])->count())->toBe($expected)
        ->and(CashboxEntry::query()->where('source_type', 'payment')->count())->toBe($expected)
        ->and(AuditLog::query()->where('action', 'payments.payment.captured')->count())->toBe($expected);
}
