<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Payments\Infrastructure\Models\Cashbox;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Modules\Tenancy\Infrastructure\Models\Tenant;
use App\Support\Audit\AuditLog;
use App\Support\Logging\LogContext;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

afterEach(function (): void {
    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();
    LogContext::clear();
});

it('grants cashbox management to owner and manager but not cashier or waiter', function (): void {
    $this->seed(DemoSeeder::class);

    $owner = cashboxBladeUser('arat-riverside', 'owner@arat.test');
    $manager = cashboxBladeUser('arat-riverside', 'manager@arat.test');
    $cashier = cashboxBladeUser('arat-riverside', 'cashier@arat.test');
    $waiter = cashboxBladeUser('arat-riverside', 'waiter@arat.test');

    expect(cashboxBladeCan($owner, 'payments.cashboxes.manage'))->toBeTrue()
        ->and(cashboxBladeCan($manager, 'payments.cashboxes.manage'))->toBeTrue()
        ->and(cashboxBladeCan($cashier, 'payments.cashboxes.manage'))->toBeFalse()
        ->and(cashboxBladeCan($waiter, 'payments.cashboxes.manage'))->toBeFalse()
        ->and(cashboxBladeCan($cashier, 'payments.capture'))->toBeTrue()
        ->and(cashboxBladeCan($manager, 'payments.capture'))->toBeTrue();
});

it('allows owner and manager to access cashbox management and blocks cashier routes and navigation', function (): void {
    $this->seed(DemoSeeder::class);

    $owner = cashboxBladeContext('arat-riverside', 'owner@arat.test', 'arat-kentron');
    $manager = cashboxBladeContext('arat-riverside', 'manager@arat.test', 'arat-kentron');
    $cashier = cashboxBladeContext('arat-riverside', 'cashier@arat.test', 'arat-kentron');

    $this->actingAs($owner['user'])
        ->withSession(['branch_id' => $owner['branch_id']])
        ->get(route('admin.payments.cashboxes.index'))
        ->assertOk()
        ->assertSee(__('payments.cashboxes.index.heading'), false);

    $this->actingAs($manager['user'])
        ->withSession(['branch_id' => $manager['branch_id']])
        ->get(route('admin.payments.cashboxes.index'))
        ->assertOk()
        ->assertSee('Main cashbox', false);

    $this->actingAs($cashier['user'])
        ->withSession(['branch_id' => $cashier['branch_id']])
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertDontSee(__('admin.nav.cashboxes'), false);

    $this->actingAs($cashier['user'])
        ->withSession(['branch_id' => $cashier['branch_id']])
        ->get(route('admin.payments.cashboxes.index'))
        ->assertForbidden();
});

it('runs cashbox create edit default deactivate and activate through Blade routes', function (): void {
    $this->seed(DemoSeeder::class);
    $manager = cashboxBladeContext('arat-riverside', 'manager@arat.test', 'arat-kentron');

    $this->actingAs($manager['user'])
        ->withHeader('X-Request-Id', 'cashbox-blade-request')
        ->withSession(['branch_id' => $manager['branch_id']])
        ->post(route('admin.payments.cashboxes.store'), ['name' => '  Side register  '])
        ->assertRedirect(route('admin.payments.cashboxes.index'));

    cashboxBladeSetContext($manager);
    $cashbox = Cashbox::query()->where('name', 'Side register')->firstOrFail();

    expect($cashbox->is_active)->toBeTrue()
        ->and($cashbox->is_default)->toBeFalse();

    $this->actingAs($manager['user'])
        ->withSession(['branch_id' => $manager['branch_id']])
        ->get(route('admin.payments.cashboxes.edit', ['cashbox' => (int) $cashbox->id]))
        ->assertOk()
        ->assertSee('Side register', false);

    $this->actingAs($manager['user'])
        ->withHeader('X-Request-Id', 'cashbox-blade-request')
        ->withSession(['branch_id' => $manager['branch_id']])
        ->put(route('admin.payments.cashboxes.update', ['cashbox' => (int) $cashbox->id]), ['name' => 'Side updated'])
        ->assertRedirect(route('admin.payments.cashboxes.index'));

    $this->actingAs($manager['user'])
        ->withHeader('X-Request-Id', 'cashbox-blade-request')
        ->withSession(['branch_id' => $manager['branch_id']])
        ->post(route('admin.payments.cashboxes.default', ['cashbox' => (int) $cashbox->id]))
        ->assertRedirect(route('admin.payments.cashboxes.index'));

    $this->actingAs($manager['user'])
        ->withHeader('X-Request-Id', 'cashbox-blade-request')
        ->withSession(['branch_id' => $manager['branch_id']])
        ->post(route('admin.payments.cashboxes.deactivate', ['cashbox' => (int) $cashbox->id]), [
            'replacement_default_id' => (int) Cashbox::query()->where('name', 'Main cashbox')->value('id'),
        ])
        ->assertRedirect(route('admin.payments.cashboxes.index'));

    $this->actingAs($manager['user'])
        ->withHeader('X-Request-Id', 'cashbox-blade-request')
        ->withSession(['branch_id' => $manager['branch_id']])
        ->post(route('admin.payments.cashboxes.activate', ['cashbox' => (int) $cashbox->id]))
        ->assertRedirect(route('admin.payments.cashboxes.index'));

    cashboxBladeSetContext($manager);
    $cashbox->refresh();

    $actions = AuditLog::query()
        ->where('target_type', 'payments_cashbox')
        ->orderBy('id')
        ->pluck('action')
        ->all();

    expect($cashbox->name)->toBe('Side updated')
        ->and($cashbox->is_active)->toBeTrue()
        ->and($actions)->toContain('payments.cashbox.created')
        ->and($actions)->toContain('payments.cashbox.updated')
        ->and($actions)->toContain('payments.cashbox.default_selected')
        ->and($actions)->toContain('payments.cashbox.deactivated')
        ->and($actions)->toContain('payments.cashbox.activated')
        ->and(AuditLog::query()->where('target_type', 'payments_cashbox')->firstOrFail()->correlation_id)->toBe('cashbox-blade-request');
});

it('renders translated validation and domain errors through Blade routes', function (): void {
    $this->seed(DemoSeeder::class);
    $manager = cashboxBladeContext('arat-riverside', 'manager@arat.test', 'arat-kentron');

    $response = $this->actingAs($manager['user'])
        ->withSession(['branch_id' => $manager['branch_id']])
        ->post(route('admin.payments.cashboxes.store'), ['name' => '   '])
        ->assertSessionHasErrors(['name']);

    expect($response->baseResponse->getSession()?->get('errors')?->first('name'))
        ->toBe(__('validation.required', ['attribute' => __('payments.cashboxes.fields.name')]));

    $response = $this->actingAs($manager['user'])
        ->withSession(['branch_id' => $manager['branch_id']])
        ->post(route('admin.payments.cashboxes.store'), ['name' => 'MAIN CASHBOX'])
        ->assertSessionHasErrors(['payments']);

    expect($response->baseResponse->getSession()?->get('errors')?->first('payments'))
        ->toBe(__('payments.cashbox_name_duplicate'));
});

it('returns not found for inaccessible branch and foreign tenant cashbox ids', function (): void {
    $this->seed(DemoSeeder::class);
    $manager = cashboxBladeContext('arat-riverside', 'manager@arat.test', 'arat-kentron');
    $otherBranch = cashboxBladeContext('arat-riverside', 'manager@arat.test', 'arat-dilijan');
    $foreignTenant = cashboxBladeContext('northstar-bistro', 'manager@northstar.test', 'northstar-downtown');

    cashboxBladeSetContext($otherBranch);
    $otherBranchCashbox = Cashbox::query()
        ->where('branch_id', $otherBranch['branch_id'])
        ->firstOrFail();
    cashboxBladeSetContext($foreignTenant);
    $foreignTenantCashbox = Cashbox::query()
        ->where('branch_id', $foreignTenant['branch_id'])
        ->firstOrFail();

    app(TenantResolver::class)->clear();
    app(BranchContext::class)->clear();

    $this->actingAs($manager['user'])
        ->withSession(['branch_id' => $manager['branch_id']])
        ->get(route('admin.payments.cashboxes.edit', ['cashbox' => (int) $otherBranchCashbox->id]))
        ->assertNotFound();

    $this->actingAs($manager['user'])
        ->withSession(['branch_id' => $manager['branch_id']])
        ->put(route('admin.payments.cashboxes.update', ['cashbox' => (int) $foreignTenantCashbox->id]), ['name' => 'Leak'])
        ->assertNotFound();
});

it('does not expose physical delete behavior for cashboxes', function (): void {
    expect(Route::has('admin.payments.cashboxes.destroy'))->toBeFalse()
        ->and(Route::has('admin.payments.cashboxes.force-delete'))->toBeFalse()
        ->and(class_exists('App\Modules\Payments\Application\DeleteCashbox'))->toBeFalse()
        ->and(class_exists('App\Modules\Payments\Application\ForceDeleteCashbox'))->toBeFalse();
});

/**
 * @return array{tenant: Tenant, user: User, branch_id: int}
 */
function cashboxBladeContext(string $tenantSlug, string $email, string $branchKey): array
{
    $tenant = Tenant::query()->where('slug', $tenantSlug)->firstOrFail();

    app(TenantResolver::class)->set((int) $tenant->id);

    $user = User::query()->where('email', $email)->firstOrFail();
    $branchId = cashboxBladeBranchId($branchKey);

    app(BranchContext::class)->set($branchId);

    return [
        'tenant' => $tenant,
        'user' => $user,
        'branch_id' => $branchId,
    ];
}

function cashboxBladeUser(string $tenantSlug, string $email): User
{
    $tenant = Tenant::query()->where('slug', $tenantSlug)->firstOrFail();

    app(TenantResolver::class)->set((int) $tenant->id);

    return User::query()->where('email', $email)->firstOrFail();
}

function cashboxBladeCan(User $user, string $permission): bool
{
    app(TenantResolver::class)->set((int) $user->tenant_id);

    return $user->can($permission);
}

/**
 * @param  array{tenant: Tenant, user: User, branch_id: int}  $context
 */
function cashboxBladeSetContext(array $context): void
{
    app(TenantResolver::class)->set((int) $context['tenant']->id);
    app(BranchContext::class)->set($context['branch_id']);
    auth()->login($context['user']);
    LogContext::start('cashbox-blade-context', 'payments');
}

function cashboxBladeBranchId(string $branchKey): int
{
    $name = match ($branchKey) {
        'arat-kentron' => 'Arat Kentron',
        'arat-dilijan' => 'Arat Dilijan Terrace',
        'northstar-downtown' => 'Northstar Downtown',
        default => throw new InvalidArgumentException('Unknown demo branch key.'),
    };

    $branchId = DB::table('branches')->where('name', $name)->value('id');

    expect($branchId)->toBeInt();

    return (int) $branchId;
}
