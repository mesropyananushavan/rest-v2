<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Models\Permission;
use App\Modules\Identity\Infrastructure\Models\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Identity\Infrastructure\Models\UserBranchAssignment;
use App\Modules\Tables\Application\ArchiveHall;
use App\Modules\Tables\Application\CreateHall;
use App\Modules\Tables\Infrastructure\Models\Hall;
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

it('runs hall CRUD through authenticated Blade routes and writes audit rows', function (): void {
    $manager = hallsBladeUser('tenant-a', 'manager-a', ['tables.halls.manage']);

    $this->actingAs($manager['user'])
        ->withHeader('X-Request-Id', 'halls-blade-request')
        ->withSession(['branch_id' => (int) $manager['branch']->id])
        ->get(route('admin.tables.halls.index'))
        ->assertOk()
        ->assertSee(__('tables.halls.index.heading'), false)
        ->assertSee(__('tables.halls.empty.title'), false);

    $this->actingAs($manager['user'])
        ->withHeader('X-Request-Id', 'halls-blade-request')
        ->withSession(['branch_id' => (int) $manager['branch']->id])
        ->post(route('admin.tables.halls.store'), hallsBladePayload('Main Hall', '#5FA8D3'))
        ->assertRedirect(route('admin.tables.halls.index'));

    app(TenantResolver::class)->set((int) $manager['tenant']->id);
    app(BranchContext::class)->set((int) $manager['branch']->id);

    $hall = Hall::query()->firstOrFail();

    $this->actingAs($manager['user'])
        ->withSession(['branch_id' => (int) $manager['branch']->id])
        ->get(route('admin.tables.halls.edit', ['hall' => (int) $hall->id]))
        ->assertOk()
        ->assertSee('Main Hall', false)
        ->assertSee('name="color"', false);

    $this->actingAs($manager['user'])
        ->withHeader('X-Request-Id', 'halls-blade-request')
        ->withSession(['branch_id' => (int) $manager['branch']->id])
        ->put(route('admin.tables.halls.update', ['hall' => (int) $hall->id]), hallsBladePayload('Main Room', '#D36B5F', active: false))
        ->assertRedirect(route('admin.tables.halls.index'));

    $this->actingAs($manager['user'])
        ->withHeader('X-Request-Id', 'halls-blade-request')
        ->withSession(['branch_id' => (int) $manager['branch']->id])
        ->delete(route('admin.tables.halls.destroy', ['hall' => (int) $hall->id]))
        ->assertRedirect(route('admin.tables.halls.index'));

    expect(Hall::withTrashed()->findOrFail((int) $hall->id)->trashed())->toBeTrue()
        ->and(AuditLog::query()->orderBy('id')->pluck('action')->all())->toBe([
            'tables.hall.created',
            'tables.hall.updated',
            'tables.hall.archived',
        ])
        ->and(AuditLog::query()->firstOrFail()->correlation_id)->toBe('halls-blade-request');
});

it('requires the halls manage permission', function (): void {
    $denied = hallsBladeUser('tenant-a', 'waiter-a', []);

    $this->actingAs($denied['user'])
        ->withSession(['branch_id' => (int) $denied['branch']->id])
        ->get(route('admin.tables.halls.index'))
        ->assertForbidden();
});

it('keeps archive visibility restore and permanent delete superadmin only', function (): void {
    $manager = hallsBladeUser('tenant-a', 'manager-a', ['tables.halls.manage']);

    hallsBladeContext($manager);
    $hall = app(CreateHall::class)(hallsBladeText('Archive Candidate'), '#5FA8D3');
    app(ArchiveHall::class)((int) $hall->id);
    app(TenantResolver::class)->clear();
    app(BranchContext::class)->clear();

    $this->actingAs($manager['user'])
        ->withSession(['branch_id' => (int) $manager['branch']->id])
        ->get(route('admin.tables.halls.index', ['archive_mode' => 'archived']))
        ->assertOk()
        ->assertDontSee(__('tables.halls.archive_modes.archived'), false)
        ->assertDontSee(__('tables.halls.actions.restore'), false)
        ->assertDontSee('Archive Candidate', false);

    $this->actingAs($manager['user'])
        ->withSession(['branch_id' => (int) $manager['branch']->id])
        ->post(route('admin.tables.halls.restore', ['hall' => (int) $hall->id]))
        ->assertForbidden();

    $owner = hallsBladeUser('tenant-b', 'owner-b', ['tables.halls.manage'], superadmin: true);

    hallsBladeContext($owner);
    $ownerHall = app(CreateHall::class)(hallsBladeText('Owner Archive Candidate'), '#D36B5F');
    app(ArchiveHall::class)((int) $ownerHall->id);
    app(TenantResolver::class)->clear();
    app(BranchContext::class)->clear();

    $this->actingAs($owner['user'])
        ->withSession(['branch_id' => (int) $owner['branch']->id])
        ->get(route('admin.tables.halls.index', ['archive_mode' => 'archived']))
        ->assertOk()
        ->assertSee(__('tables.halls.archive_modes.archived'), false)
        ->assertSee(__('tables.halls.actions.restore'), false)
        ->assertSee(__('tables.halls.actions.force_delete'), false);

    $this->actingAs($owner['user'])
        ->withSession(['branch_id' => (int) $owner['branch']->id])
        ->post(route('admin.tables.halls.restore', ['hall' => (int) $ownerHall->id]))
        ->assertRedirect(route('admin.tables.halls.index', ['archive_mode' => 'archived']));

    hallsBladeContext($owner);
    app(ArchiveHall::class)((int) $ownerHall->id);
    app(TenantResolver::class)->clear();
    app(BranchContext::class)->clear();

    $this->actingAs($owner['user'])
        ->withSession(['branch_id' => (int) $owner['branch']->id])
        ->delete(route('admin.tables.halls.force-delete', ['hall' => (int) $ownerHall->id]))
        ->assertRedirect(route('admin.tables.halls.index', ['archive_mode' => 'archived']));

    hallsBladeContext($owner);
    expect(Hall::withTrashed()->find((int) $ownerHall->id))->toBeNull();
});

it('returns 404 for foreign tenant and foreign branch hall ids', function (): void {
    $tenantA = hallsBladeUser('tenant-a', 'manager-a', ['tables.halls.manage'], branchCount: 2);
    $tenantB = hallsBladeUser('tenant-b', 'manager-b', ['tables.halls.manage']);

    hallsBladeContext($tenantA, branchIndex: 1);
    $foreignBranchHall = app(CreateHall::class)(hallsBladeText('Foreign Branch Hall'), '#5FA8D3');

    hallsBladeContext($tenantB);
    $foreignTenantHall = app(CreateHall::class)(hallsBladeText('Foreign Tenant Hall'), '#D36B5F');

    app(TenantResolver::class)->clear();
    app(BranchContext::class)->clear();

    $this->actingAs($tenantA['user'])
        ->withSession(['branch_id' => (int) $tenantA['branches'][0]->id])
        ->get(route('admin.tables.halls.edit', ['hall' => (int) $foreignBranchHall->id]))
        ->assertNotFound();

    $this->actingAs($tenantA['user'])
        ->withSession(['branch_id' => (int) $tenantA['branches'][0]->id])
        ->get(route('admin.tables.halls.edit', ['hall' => (int) $foreignTenantHall->id]))
        ->assertNotFound();
});

/**
 * @param  list<string>  $permissionCodes
 * @return array{tenant: Tenant, branch: Branch, branches: list<Branch>, user: User}
 */
function hallsBladeUser(string $tenantSlug, string $username, array $permissionCodes, bool $superadmin = false, int $branchCount = 1): array
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
function hallsBladeContext(array $record, int $branchIndex = 0): void
{
    app(TenantResolver::class)->set((int) $record['tenant']->id);
    app(BranchContext::class)->set((int) $record['branches'][$branchIndex]->id);
    auth()->login($record['user']);
    LogContext::start('halls-blade-context', 'tables');
}

/**
 * @return array<string, string|int>
 */
function hallsBladePayload(string $name, string $color, bool $active = true): array
{
    return [
        'name_hy' => $name,
        'name_ru' => $name,
        'name_en' => $name,
        'color' => $color,
        'sort_order' => 10,
        'active' => $active ? 1 : 0,
    ];
}

function hallsBladeText(string $text): LocalizedText
{
    return LocalizedText::fromArray([
        'hy' => $text,
        'ru' => $text,
        'en' => $text,
    ]);
}
