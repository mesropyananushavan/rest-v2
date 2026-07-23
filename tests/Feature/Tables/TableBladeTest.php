<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Models\Permission;
use App\Modules\Identity\Infrastructure\Models\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Identity\Infrastructure\Models\UserBranchAssignment;
use App\Modules\Tables\Application\ArchiveTable;
use App\Modules\Tables\Application\CreateHall;
use App\Modules\Tables\Application\CreateTable;
use App\Modules\Tables\Infrastructure\Models\Table;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Modules\Tenancy\Infrastructure\Models\Branch;
use App\Modules\Tenancy\Infrastructure\Models\Tenant;
use App\Support\Audit\AuditLog;
use App\Support\I18n\LocalizedText;
use App\Support\Logging\LogContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

afterEach(function (): void {
    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();
    LogContext::clear();
});

it('runs table CRUD through authenticated Blade routes and writes audit rows', function (): void {
    $manager = tablesBladeUser('tenant-a', 'manager-a', ['tables.halls.manage', 'tables.tables.manage']);

    tablesBladeContext($manager);
    $hall = app(CreateHall::class)(tablesBladeText('Main Hall'), '#5FA8D3');
    app(TenantResolver::class)->clear();
    app(BranchContext::class)->clear();

    $this->actingAs($manager['user'])
        ->withHeader('X-Request-Id', 'tables-blade-request')
        ->withSession(['branch_id' => (int) $manager['branch']->id])
        ->get(route('admin.tables.tables.index', ['hall' => (int) $hall->id]))
        ->assertOk()
        ->assertSee(__('tables.tables.index.eyebrow'), false)
        ->assertSee(__('tables.tables.empty.title'), false);

    $this->actingAs($manager['user'])
        ->withHeader('X-Request-Id', 'tables-blade-request')
        ->withSession(['branch_id' => (int) $manager['branch']->id])
        ->post(route('admin.tables.tables.store', ['hall' => (int) $hall->id]), tablesBladePayload('Table 1', type: 'standard', shape: 'square'))
        ->assertRedirect(route('admin.tables.tables.index', ['hall' => (int) $hall->id]));

    app(TenantResolver::class)->set((int) $manager['tenant']->id);
    app(BranchContext::class)->set((int) $manager['branch']->id);

    $table = Table::query()->firstOrFail();

    $this->actingAs($manager['user'])
        ->withSession(['branch_id' => (int) $manager['branch']->id])
        ->get(route('admin.tables.tables.edit', ['hall' => (int) $hall->id, 'table' => (int) $table->id]))
        ->assertOk()
        ->assertSee('Table 1', false)
        ->assertSee('name="shape"', false);

    $this->actingAs($manager['user'])
        ->withHeader('X-Request-Id', 'tables-blade-request')
        ->withSession(['branch_id' => (int) $manager['branch']->id])
        ->put(route('admin.tables.tables.update', ['hall' => (int) $hall->id, 'table' => (int) $table->id]), tablesBladePayload('VIP', type: 'vip', shape: 'rectangle', active: false, isDelivery: true))
        ->assertRedirect(route('admin.tables.tables.index', ['hall' => (int) $hall->id]));

    $this->actingAs($manager['user'])
        ->withHeader('X-Request-Id', 'tables-blade-request')
        ->withSession(['branch_id' => (int) $manager['branch']->id])
        ->delete(route('admin.tables.tables.destroy', ['hall' => (int) $hall->id, 'table' => (int) $table->id]))
        ->assertRedirect(route('admin.tables.tables.index', ['hall' => (int) $hall->id]));

    expect(Table::withTrashed()->findOrFail((int) $table->id)->trashed())->toBeTrue()
        ->and(AuditLog::query()->where('target_type', 'tables_table')->orderBy('id')->pluck('action')->all())->toBe([
            'tables.table.created',
            'tables.table.updated',
            'tables.table.archived',
        ])
        ->and(AuditLog::query()->where('target_type', 'tables_table')->firstOrFail()->correlation_id)->toBe('tables-blade-request');
});

it('requires the tables manage permission', function (): void {
    $denied = tablesBladeUser('tenant-a', 'manager-a', ['tables.halls.manage']);

    tablesBladeContext($denied);
    $hall = app(CreateHall::class)(tablesBladeText('Main Hall'), '#5FA8D3');
    app(TenantResolver::class)->clear();
    app(BranchContext::class)->clear();

    $this->actingAs($denied['user'])
        ->withSession(['branch_id' => (int) $denied['branch']->id])
        ->get(route('admin.tables.tables.index', ['hall' => (int) $hall->id]))
        ->assertForbidden();
});

it('keeps table archive visibility restore and permanent delete superadmin only', function (): void {
    $manager = tablesBladeUser('tenant-a', 'manager-a', ['tables.halls.manage', 'tables.tables.manage']);

    tablesBladeContext($manager);
    $hall = app(CreateHall::class)(tablesBladeText('Archive Hall'), '#5FA8D3');
    $table = app(CreateTable::class)((int) $hall->id, tablesBladeText('Archive Candidate'));
    app(ArchiveTable::class)((int) $hall->id, (int) $table->id);
    app(TenantResolver::class)->clear();
    app(BranchContext::class)->clear();

    $this->actingAs($manager['user'])
        ->withSession(['branch_id' => (int) $manager['branch']->id])
        ->get(route('admin.tables.tables.index', ['hall' => (int) $hall->id, 'archive_mode' => 'archived']))
        ->assertOk()
        ->assertDontSee(__('tables.tables.archive_modes.archived'), false)
        ->assertDontSee(__('tables.tables.actions.restore'), false)
        ->assertDontSee('Archive Candidate', false);

    $this->actingAs($manager['user'])
        ->withSession(['branch_id' => (int) $manager['branch']->id])
        ->post(route('admin.tables.tables.restore', ['hall' => (int) $hall->id, 'table' => (int) $table->id]))
        ->assertForbidden();

    $this->actingAs($manager['user'])
        ->withSession(['branch_id' => (int) $manager['branch']->id])
        ->delete(route('admin.tables.tables.force-delete', ['hall' => (int) $hall->id, 'table' => (int) $table->id]))
        ->assertForbidden();

    $owner = tablesBladeUser('tenant-b', 'owner-b', ['tables.halls.manage', 'tables.tables.manage'], superadmin: true);

    tablesBladeContext($owner);
    $ownerHall = app(CreateHall::class)(tablesBladeText('Owner Archive Hall'), '#D36B5F');
    $ownerTable = app(CreateTable::class)((int) $ownerHall->id, tablesBladeText('Owner Archive Candidate'));
    app(ArchiveTable::class)((int) $ownerHall->id, (int) $ownerTable->id);
    app(TenantResolver::class)->clear();
    app(BranchContext::class)->clear();

    $this->actingAs($owner['user'])
        ->withSession(['branch_id' => (int) $owner['branch']->id])
        ->get(route('admin.tables.tables.index', ['hall' => (int) $ownerHall->id, 'archive_mode' => 'archived']))
        ->assertOk()
        ->assertSee(__('tables.tables.archive_modes.archived'), false)
        ->assertSee(__('tables.tables.actions.restore'), false)
        ->assertSee(__('tables.tables.actions.force_delete'), false);

    $this->actingAs($owner['user'])
        ->withSession(['branch_id' => (int) $owner['branch']->id])
        ->post(route('admin.tables.tables.restore', ['hall' => (int) $ownerHall->id, 'table' => (int) $ownerTable->id]))
        ->assertRedirect(route('admin.tables.tables.index', ['hall' => (int) $ownerHall->id, 'archive_mode' => 'archived']));

    tablesBladeContext($owner);
    app(ArchiveTable::class)((int) $ownerHall->id, (int) $ownerTable->id);
    app(TenantResolver::class)->clear();
    app(BranchContext::class)->clear();

    $this->actingAs($owner['user'])
        ->withSession(['branch_id' => (int) $owner['branch']->id])
        ->delete(route('admin.tables.tables.force-delete', ['hall' => (int) $ownerHall->id, 'table' => (int) $ownerTable->id]))
        ->assertRedirect(route('admin.tables.tables.index', ['hall' => (int) $ownerHall->id, 'archive_mode' => 'archived']));

    tablesBladeContext($owner);
    expect(Table::withTrashed()->find((int) $ownerTable->id))->toBeNull();
});

it('returns 404 for foreign tenant foreign branch and foreign hall table ids', function (): void {
    $tenantA = tablesBladeUser('tenant-a', 'manager-a', ['tables.halls.manage', 'tables.tables.manage'], branchCount: 2);
    $tenantB = tablesBladeUser('tenant-b', 'manager-b', ['tables.halls.manage', 'tables.tables.manage']);

    tablesBladeContext($tenantA, branchIndex: 0);
    $visibleHall = app(CreateHall::class)(tablesBladeText('Visible Hall'), '#5FA8D3');
    $visibleTable = app(CreateTable::class)((int) $visibleHall->id, tablesBladeText('Visible Table'));
    $otherHall = app(CreateHall::class)(tablesBladeText('Other Hall'), '#78CD51');
    $foreignHallTable = app(CreateTable::class)((int) $otherHall->id, tablesBladeText('Foreign Hall Table'));

    tablesBladeContext($tenantA, branchIndex: 1);
    $foreignBranchHall = app(CreateHall::class)(tablesBladeText('Foreign Branch Hall'), '#D36B5F');
    $foreignBranchTable = app(CreateTable::class)((int) $foreignBranchHall->id, tablesBladeText('Foreign Branch Table'));

    tablesBladeContext($tenantB);
    $foreignTenantHall = app(CreateHall::class)(tablesBladeText('Foreign Tenant Hall'), '#8B6FD3');
    $foreignTenantTable = app(CreateTable::class)((int) $foreignTenantHall->id, tablesBladeText('Foreign Tenant Table'));

    app(TenantResolver::class)->clear();
    app(BranchContext::class)->clear();

    $this->actingAs($tenantA['user'])
        ->withSession(['branch_id' => (int) $tenantA['branches'][0]->id])
        ->get(route('admin.tables.tables.index', ['hall' => (int) $visibleHall->id]))
        ->assertOk()
        ->assertSee('Visible Table', false)
        ->assertDontSee('Foreign Hall Table', false)
        ->assertDontSee('Foreign Branch Table', false)
        ->assertDontSee('Foreign Tenant Table', false);

    $this->actingAs($tenantA['user'])
        ->withSession(['branch_id' => (int) $tenantA['branches'][0]->id])
        ->get(route('admin.tables.tables.edit', ['hall' => (int) $visibleHall->id, 'table' => (int) $foreignHallTable->id]))
        ->assertNotFound();

    $this->actingAs($tenantA['user'])
        ->withSession(['branch_id' => (int) $tenantA['branches'][0]->id])
        ->get(route('admin.tables.tables.edit', ['hall' => (int) $foreignBranchHall->id, 'table' => (int) $foreignBranchTable->id]))
        ->assertNotFound();

    $this->actingAs($tenantA['user'])
        ->withSession(['branch_id' => (int) $tenantA['branches'][0]->id])
        ->get(route('admin.tables.tables.edit', ['hall' => (int) $visibleHall->id, 'table' => (int) $foreignTenantTable->id]))
        ->assertNotFound();

    expect((int) $visibleTable->id)->toBeGreaterThan(0);
});

/**
 * @param  list<string>  $permissionCodes
 * @return array{tenant: Tenant, branch: Branch, branches: list<Branch>, user: User}
 */
function tablesBladeUser(string $tenantSlug, string $username, array $permissionCodes, bool $superadmin = false, int $branchCount = 1): array
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

    app(BranchContext::class)->set((int) $branches[0]->id);

    $role = Role::query()->create([
        'code' => "{$username}-role",
        'name' => "{$username} Role",
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
        'default_locale' => 'en',
        'active' => true,
        'is_superadmin' => $superadmin,
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
        'branch' => $branches[0],
        'branches' => $branches,
        'user' => $user,
    ];
}

/**
 * @param  array{tenant: Tenant, branches: list<Branch>, user: User}  $record
 */
function tablesBladeContext(array $record, int $branchIndex = 0): void
{
    app(TenantResolver::class)->set((int) $record['tenant']->id);
    app(BranchContext::class)->set((int) $record['branches'][$branchIndex]->id);
    auth()->login($record['user']);
    LogContext::start('tables-blade-context', 'tables');
}

/**
 * @return array<string, string|int>
 */
function tablesBladePayload(string $name, string $type, string $shape, bool $active = true, bool $isDelivery = false): array
{
    return [
        'name_hy' => $name,
        'name_ru' => $name,
        'name_en' => $name,
        'type' => $type,
        'shape' => $shape,
        'hdm_department' => 1,
        'sort_order' => 10,
        'active' => $active ? 1 : 0,
        'is_delivery' => $isDelivery ? 1 : 0,
    ];
}

function tablesBladeText(string $text): LocalizedText
{
    return LocalizedText::fromArray([
        'hy' => $text,
        'ru' => $text,
        'en' => $text,
    ]);
}
