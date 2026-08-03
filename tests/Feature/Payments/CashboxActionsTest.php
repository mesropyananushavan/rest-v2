<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Models\Permission;
use App\Modules\Identity\Infrastructure\Models\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Identity\Infrastructure\Models\UserBranchAssignment;
use App\Modules\Payments\Application\ActivateCashbox;
use App\Modules\Payments\Application\CreateCashbox;
use App\Modules\Payments\Application\DeactivateCashbox;
use App\Modules\Payments\Application\SelectDefaultCashbox;
use App\Modules\Payments\Application\UpdateCashbox;
use App\Modules\Payments\Domain\PaymentsDomainException;
use App\Modules\Payments\Infrastructure\Models\Cashbox;
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

it('trims names creates the first active cashbox as default and writes audit', function (): void {
    $record = cashboxActionsUser('tenant-a', 'manager-a');
    cashboxActionsActingIn($record, 0, 'cashbox-create-request');

    $cashbox = app(CreateCashbox::class)('  Main register  ');

    expect($cashbox->name)->toBe('Main register')
        ->and($cashbox->is_active)->toBeTrue()
        ->and($cashbox->is_default)->toBeTrue()
        ->and((int) $cashbox->tenant_id)->toBe((int) $record['tenant']->id)
        ->and((int) $cashbox->branch_id)->toBe((int) $record['branches'][0]->id);

    $audit = AuditLog::query()->where('action', 'payments.cashbox.created')->firstOrFail();

    expect($audit->tenant_id)->toBe((int) $record['tenant']->id)
        ->and($audit->branch_id)->toBe((int) $record['branches'][0]->id)
        ->and($audit->actor_id)->toBe((int) $record['user']->id)
        ->and($audit->target_type)->toBe('payments_cashbox')
        ->and($audit->target_id)->toBe((int) $cashbox->id)
        ->and($audit->correlation_id)->toBe('cashbox-create-request')
        ->and($audit->before_json)->toBeNull()
        ->and($audit->after_json['name'])->toBe('Main register')
        ->and($audit->after_json['is_default'])->toBeTrue();
});

it('validates cashbox names and leaves no partial state or success audit rows on failure', function (): void {
    $record = cashboxActionsUser('tenant-a', 'manager-a');
    cashboxActionsActingIn($record, 0, 'cashbox-validation-request');

    foreach ([
        '   ' => 'payments.cashbox_name_required',
        str_repeat('A', 256) => 'payments.cashbox_name_too_long',
    ] as $name => $errorCode) {
        try {
            app(CreateCashbox::class)($name);
            $this->fail("Expected {$errorCode}.");
        } catch (PaymentsDomainException $exception) {
            expect($exception->errorCode())->toBe($errorCode);
        }
    }

    expect(Cashbox::query()->count())->toBe(0)
        ->and(AuditLog::query()->where('action', 'payments.cashbox.created')->count())->toBe(0);
});

it('rejects duplicate active names only inside the same tenant and branch', function (): void {
    $tenantA = cashboxActionsUser('tenant-a', 'manager-a', branchCount: 2);
    $tenantB = cashboxActionsUser('tenant-b', 'manager-b');

    cashboxActionsActingIn($tenantA, 0, 'cashbox-duplicate-a');
    app(CreateCashbox::class)('Main Register');

    try {
        app(CreateCashbox::class)('main register');
        $this->fail('Expected duplicate active cashbox name.');
    } catch (PaymentsDomainException $exception) {
        expect($exception->errorCode())->toBe('payments.cashbox_name_duplicate');
    }

    cashboxActionsActingIn($tenantA, 1, 'cashbox-duplicate-branch-b');
    $sameNameOtherBranch = app(CreateCashbox::class)('main register');

    cashboxActionsActingIn($tenantB, 0, 'cashbox-duplicate-tenant-b');
    $sameNameOtherTenant = app(CreateCashbox::class)('main register');

    expect((int) $sameNameOtherBranch->branch_id)->toBe((int) $tenantA['branches'][1]->id)
        ->and((int) $sameNameOtherTenant->tenant_id)->toBe((int) $tenantB['tenant']->id);
});

it('allows inactive name reuse and rejects activation while an active duplicate exists', function (): void {
    $record = cashboxActionsUser('tenant-a', 'manager-a');
    cashboxActionsActingIn($record, 0, 'cashbox-inactive-reuse');

    $inactive = app(CreateCashbox::class)('Archive Register', isActive: false);
    $active = app(CreateCashbox::class)('archive register');

    expect($inactive->is_active)->toBeFalse()
        ->and($active->is_active)->toBeTrue();

    try {
        app(ActivateCashbox::class)((int) $inactive->id);
        $this->fail('Expected duplicate active cashbox name on activation.');
    } catch (PaymentsDomainException $exception) {
        expect($exception->errorCode())->toBe('payments.cashbox_name_duplicate');
    }
});

it('keeps exactly one active default through default selection and lifecycle changes', function (): void {
    $record = cashboxActionsUser('tenant-a', 'manager-a');
    cashboxActionsActingIn($record, 0, 'cashbox-defaults');

    $main = app(CreateCashbox::class)('Main');
    $bar = app(CreateCashbox::class)('Bar');

    expect(cashboxActionsActiveDefaultIds())->toBe([(int) $main->id]);

    app(SelectDefaultCashbox::class)((int) $bar->id);

    expect(cashboxActionsActiveDefaultIds())->toBe([(int) $bar->id]);

    try {
        app(DeactivateCashbox::class)((int) $bar->id);
        $this->fail('Expected replacement requirement.');
    } catch (PaymentsDomainException $exception) {
        expect($exception->errorCode())->toBe('payments.cashbox_default_replacement_required');
    }

    expect(cashboxActionsActiveDefaultIds())->toBe([(int) $bar->id])
        ->and(Cashbox::query()->where('is_active', true)->count())->toBe(2);

    app(DeactivateCashbox::class)((int) $bar->id, (int) $main->id);

    expect(Cashbox::query()->findOrFail((int) $bar->id)->is_active)->toBeFalse()
        ->and(cashboxActionsActiveDefaultIds())->toBe([(int) $main->id])
        ->and(AuditLog::query()->where('action', 'payments.cashbox.default_selected')->count())->toBe(2)
        ->and(AuditLog::query()->where('action', 'payments.cashbox.deactivated')->count())->toBe(1);

    app(DeactivateCashbox::class)((int) $main->id);

    expect(cashboxActionsActiveDefaultIds())->toBe([])
        ->and(Cashbox::query()->where('is_active', true)->count())->toBe(0);
});

it('updates names audits successful mutations and rejects inactive default selection', function (): void {
    $record = cashboxActionsUser('tenant-a', 'manager-a');
    cashboxActionsActingIn($record, 0, 'cashbox-update');

    $main = app(CreateCashbox::class)('Main');
    $inactive = app(CreateCashbox::class)('Inactive', isActive: false);

    app(UpdateCashbox::class)((int) $main->id, ' Front register ');

    expect(Cashbox::query()->findOrFail((int) $main->id)->name)->toBe('Front register')
        ->and(AuditLog::query()->where('action', 'payments.cashbox.updated')->count())->toBe(1);

    try {
        app(SelectDefaultCashbox::class)((int) $inactive->id);
        $this->fail('Expected active default requirement.');
    } catch (PaymentsDomainException $exception) {
        expect($exception->errorCode())->toBe('payments.cashbox_default_must_be_active');
    }

    expect(cashboxActionsActiveDefaultIds())->toBe([(int) $main->id]);
});

/**
 * @return array{tenant: Tenant, branches: list<Branch>, user: User}
 */
function cashboxActionsUser(string $tenantSlug, string $username, int $branchCount = 1): array
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
    $permission = Permission::query()->create([
        'code' => 'payments.cashboxes.manage',
        'name' => 'Manage cashboxes',
    ]);
    $role->permissions()->attach((int) $permission->id, ['tenant_id' => (int) $tenant->id]);

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

    app(TenantResolver::class)->clear();
    app(BranchContext::class)->clear();

    return [
        'tenant' => $tenant,
        'branches' => $branches,
        'user' => $user,
    ];
}

/**
 * @param  array{tenant: Tenant, branches: list<Branch>, user: User}  $record
 */
function cashboxActionsActingIn(array $record, int $branchIndex, string $requestId): void
{
    app(TenantResolver::class)->set((int) $record['tenant']->id);
    app(BranchContext::class)->set((int) $record['branches'][$branchIndex]->id);
    auth()->login($record['user']);
    LogContext::start($requestId, 'payments');
}

/**
 * @return list<int>
 */
function cashboxActionsActiveDefaultIds(): array
{
    return Cashbox::query()
        ->where('is_active', true)
        ->where('is_default', true)
        ->orderBy('id')
        ->pluck('id')
        ->map(fn (int|string $id): int => (int) $id)
        ->all();
}
