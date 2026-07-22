<?php

declare(strict_types=1);

use App\Modules\Identity\Contracts\Authorizer;
use App\Modules\Identity\Infrastructure\Models\Permission;
use App\Modules\Identity\Infrastructure\Models\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Identity\Infrastructure\Models\UserBranchAssignment;
use App\Modules\Menu\Infrastructure\Models\MenuCategory;
use App\Modules\Menu\Infrastructure\Models\MenuItem;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Modules\Tenancy\Infrastructure\Models\Branch;
use App\Modules\Tenancy\Infrastructure\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Tests\Support\Jobs\RecordTenantScopedBranchIdsJob;

uses(RefreshDatabase::class);

afterEach(function (): void {
    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();
});

it('prevents a user from tenant A from seeing tenant B identity or branch data', function (): void {
    $tenantA = tenantWithUser('tenant-a', 'manager-a', ['menu.items.manage']);
    $tenantB = tenantWithUser('tenant-b', 'manager-b', ['menu.items.manage']);
    $unscopedVisibleRows = usesPostgresRowLevelSecurity() ? 1 : 2;

    app(TenantResolver::class)->set((int) $tenantA['tenant']->id);

    expect(User::query()->pluck('username')->all())->toContain('manager-a')
        ->not->toContain('manager-b')
        ->and(Branch::query()->pluck('name')->all())->toContain('tenant-a Branch')
        ->not->toContain('tenant-b Branch')
        ->and(User::query()->find((int) $tenantB['user']->id))->toBeNull()
        ->and(Branch::query()->find((int) $tenantB['branch']->id))->toBeNull()
        ->and(User::withoutGlobalScopes()->count())->toBe($unscopedVisibleRows)
        ->and(Branch::withoutGlobalScopes()->count())->toBe($unscopedVisibleRows);

    app(TenantResolver::class)->clear();

    expect(User::query()->count())->toBe(0)
        ->and(Branch::query()->count())->toBe(0);
});

it('prevents writes and deletes against another tenant through scoped Eloquent operations', function (): void {
    $tenantA = tenantWithUser('tenant-a', 'manager-a', ['menu.items.manage']);
    $tenantB = tenantWithUser('tenant-b', 'manager-b', ['menu.items.manage']);

    app(TenantResolver::class)->set((int) $tenantA['tenant']->id);

    $updatedByWhere = User::query()
        ->where('id', (int) $tenantB['user']->id)
        ->update(['name' => 'Compromised User']);

    $updatedByWhereKey = Branch::query()
        ->whereKey((int) $tenantB['branch']->id)
        ->update(['name' => 'Compromised Branch']);

    $deletedByWhere = User::query()
        ->where('id', (int) $tenantB['user']->id)
        ->delete();

    $deletedByWhereKey = Branch::query()
        ->whereKey((int) $tenantB['branch']->id)
        ->delete();

    expect($updatedByWhere)->toBe(0)
        ->and($updatedByWhereKey)->toBe(0)
        ->and($deletedByWhere)->toBe(0)
        ->and($deletedByWhereKey)->toBe(0);

    app(TenantResolver::class)->set((int) $tenantB['tenant']->id);

    expect(User::withoutGlobalScopes()->find((int) $tenantB['user']->id)?->name)->toBe('manager-b')
        ->and(Branch::withoutGlobalScopes()->find((int) $tenantB['branch']->id)?->name)->toBe('tenant-b Branch');
});

it('forces created records into the current tenant even when a foreign tenant id is supplied', function (): void {
    $tenantA = tenantWithUser('tenant-a', 'manager-a', ['menu.items.manage']);
    $tenantB = tenantWithUser('tenant-b', 'manager-b', ['menu.items.manage']);

    app(TenantResolver::class)->set((int) $tenantA['tenant']->id);

    $branch = Branch::query()->create([
        'tenant_id' => (int) $tenantB['tenant']->id,
        'name' => 'Tenant Override Attempt',
        'timezone' => 'Asia/Yerevan',
        'status' => 'active',
    ]);

    expect((int) $branch->tenant_id)->toBe((int) $tenantA['tenant']->id)
        ->and(Branch::withoutGlobalScopes()->find((int) $branch->id)?->tenant_id)->toBe($tenantA['tenant']->id);
});

it('returns 404 when an authenticated user requests another tenant resource by id', function (): void {
    $tenantA = tenantWithUser('tenant-a', 'manager-a', ['menu.items.manage']);
    $tenantB = tenantWithUser('tenant-b', 'manager-b', ['menu.items.manage']);

    $this->actingAs($tenantA['user'])
        ->get(route('admin.branches.show', ['branch' => (int) $tenantA['branch']->id]))
        ->assertOk()
        ->assertJsonPath('data.id', (int) $tenantA['branch']->id);

    $this->actingAs($tenantA['user'])
        ->get(route('admin.branches.show', ['branch' => (int) $tenantB['branch']->id]))
        ->assertNotFound();
});

it('resolves tenant and branch context from request headers', function (): void {
    $tenant = tenantWithUser('tenant-a', 'manager-a', ['menu.items.manage']);

    Route::middleware('web')->get('/_test/context', fn () => response()->json([
        'tenant_id' => app(TenantResolver::class)->id(),
        'branch_id' => app(BranchContext::class)->id(),
        'locale' => app()->getLocale(),
    ]));

    $this->withHeader('X-Tenant-ID', (string) $tenant['tenant']->id)
        ->withHeader('X-Branch-ID', (string) $tenant['branch']->id)
        ->get('/_test/context')
        ->assertOk()
        ->assertJson([
            'tenant_id' => (int) $tenant['tenant']->id,
            'branch_id' => (int) $tenant['branch']->id,
            'locale' => 'hy',
        ]);
});

it('ignores tenant header in production', function (): void {
    $tenant = tenantWithUser('tenant-a', 'manager-a', ['menu.items.manage']);

    app()->detectEnvironment(fn (): string => 'production');

    Route::middleware('web')->get('/_test/production-context', fn () => response()->json([
        'tenant_id' => app(TenantResolver::class)->id(),
    ]));

    $this->withHeader('X-Tenant-ID', (string) $tenant['tenant']->id)
        ->get('/_test/production-context')
        ->assertOk()
        ->assertJson([
            'tenant_id' => null,
        ]);
});

it('does not allow tenant header to override an authenticated user tenant', function (): void {
    $tenantA = tenantWithUser('tenant-a', 'manager-a', ['menu.items.manage']);
    $tenantB = tenantWithUser('tenant-b', 'manager-b', ['menu.items.manage']);

    Route::middleware(['web', 'auth'])->get('/_test/authenticated-context', fn () => response()->json([
        'tenant_id' => app(TenantResolver::class)->id(),
    ]));

    $this->actingAs($tenantA['user'])
        ->withHeader('X-Tenant-ID', (string) $tenantB['tenant']->id)
        ->get('/_test/authenticated-context')
        ->assertOk()
        ->assertJson([
            'tenant_id' => (int) $tenantA['tenant']->id,
        ]);
});

it('restores tenant and branch context for tenant-scoped queries inside queued jobs', function (): void {
    $tenantA = tenantWithUser('tenant-a', 'manager-a', ['menu.items.manage']);
    $tenantB = tenantWithUser('tenant-b', 'manager-b', ['menu.items.manage']);
    $cacheKey = 'tenant-scoped-queued-job-result';

    config(['queue.default' => 'database']);
    app(TenantResolver::class)->set((int) $tenantA['tenant']->id);
    app(BranchContext::class)->set((int) $tenantA['branch']->id);

    Queue::connection('database')->push(new RecordTenantScopedBranchIdsJob($cacheKey));

    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();

    expect(Branch::query()->count())->toBe(0);

    $job = Queue::connection('database')->pop('default');

    expect($job)->not->toBeNull();
    assert($job !== null);

    Event::dispatch(new JobProcessing('database', $job));
    $job->fire();
    Event::dispatch(new JobProcessed('database', $job));

    expect(Cache::get($cacheKey))->toBe([
        'tenant_id' => (int) $tenantA['tenant']->id,
        'branch_id' => (int) $tenantA['branch']->id,
        'visible_branch_ids' => [(int) $tenantA['branch']->id],
    ])->and(app(TenantResolver::class)->id())->toBeNull()
        ->and(app(BranchContext::class)->id())->toBeNull();
});

it('enforces PostgreSQL row level security when tenant setting is missing', function (): void {
    if (! usesPostgresRowLevelSecurity()) {
        $this->markTestSkipped('PostgreSQL RLS coverage runs only on pgsql.');
    }

    $tenantA = tenantWithUser('tenant-a', 'manager-a', ['menu.items.manage']);
    $tenantB = tenantWithUser('tenant-b', 'manager-b', ['menu.items.manage']);

    app(TenantResolver::class)->clear();

    expect(rawBranchIds())->toBe([]);

    app(TenantResolver::class)->set((int) $tenantA['tenant']->id);

    expect(rawBranchIds())->toBe([(int) $tenantA['branch']->id]);

    app(TenantResolver::class)->set((int) $tenantB['tenant']->id);

    expect(rawBranchIds())->toBe([(int) $tenantB['branch']->id]);
});

it('enforces PostgreSQL row level security for menu tables', function (): void {
    if (! usesPostgresRowLevelSecurity()) {
        $this->markTestSkipped('PostgreSQL RLS coverage runs only on pgsql.');
    }

    $tenantA = tenantWithUser('tenant-a', 'manager-a', ['menu.items.manage']);
    $tenantB = tenantWithUser('tenant-b', 'manager-b', ['menu.items.manage']);

    app(TenantResolver::class)->set((int) $tenantA['tenant']->id);
    app(BranchContext::class)->set((int) $tenantA['branch']->id);

    $rootCategoryA = MenuCategory::query()->create([
        'translated_name' => ['hy' => 'Tenant A Menu', 'ru' => 'Tenant A Menu', 'en' => 'Tenant A Menu'],
        'sort_order' => 100,
        'active' => true,
    ]);

    $categoryA = MenuCategory::query()->create([
        'parent_id' => (int) $rootCategoryA->id,
        'translated_name' => ['hy' => 'Tenant A', 'ru' => 'Tenant A', 'en' => 'Tenant A'],
        'sort_order' => 10,
        'active' => true,
    ]);

    $itemA = MenuItem::query()->create([
        'branch_id' => (int) $tenantA['branch']->id,
        'category_id' => (int) $categoryA->id,
        'translated_name' => ['hy' => 'Tenant A Item', 'ru' => 'Tenant A Item', 'en' => 'Tenant A Item'],
        'translated_description' => ['hy' => 'Tenant A Description', 'ru' => 'Tenant A Description', 'en' => 'Tenant A Description'],
        'price_minor' => 100000,
        'currency' => 'AMD',
        'active' => true,
    ]);

    app(TenantResolver::class)->set((int) $tenantB['tenant']->id);
    app(BranchContext::class)->set((int) $tenantB['branch']->id);

    $rootCategoryB = MenuCategory::query()->create([
        'translated_name' => ['hy' => 'Tenant B Menu', 'ru' => 'Tenant B Menu', 'en' => 'Tenant B Menu'],
        'sort_order' => 100,
        'active' => true,
    ]);

    $categoryB = MenuCategory::query()->create([
        'parent_id' => (int) $rootCategoryB->id,
        'translated_name' => ['hy' => 'Tenant B', 'ru' => 'Tenant B', 'en' => 'Tenant B'],
        'sort_order' => 10,
        'active' => true,
    ]);

    $itemB = MenuItem::query()->create([
        'branch_id' => (int) $tenantB['branch']->id,
        'category_id' => (int) $categoryB->id,
        'translated_name' => ['hy' => 'Tenant B Item', 'ru' => 'Tenant B Item', 'en' => 'Tenant B Item'],
        'translated_description' => ['hy' => 'Tenant B Description', 'ru' => 'Tenant B Description', 'en' => 'Tenant B Description'],
        'price_minor' => 200000,
        'currency' => 'AMD',
        'active' => true,
    ]);

    app(TenantResolver::class)->clear();

    expect(rawMenuItemIds())->toBe([]);

    app(TenantResolver::class)->set((int) $tenantA['tenant']->id);

    expect(rawMenuItemIds())->toBe([(int) $itemA->id]);

    app(TenantResolver::class)->set((int) $tenantB['tenant']->id);

    expect(rawMenuItemIds())->toBe([(int) $itemB->id]);
});

it('checks action permissions through the identity authorizer contract', function (): void {
    $tenant = tenantWithUser('tenant-a', 'manager-a', ['menu.items.manage']);

    app(TenantResolver::class)->set((int) $tenant['tenant']->id);

    expect(app(Authorizer::class)->allows($tenant['user'], 'menu.items.manage'))->toBeTrue()
        ->and(app(Authorizer::class)->allows($tenant['user'], 'identity.manage'))->toBeFalse()
        ->and(Gate::forUser($tenant['user'])->allows('menu.items.manage'))->toBeTrue()
        ->and(Gate::forUser($tenant['user'])->allows('identity.manage'))->toBeFalse();
});

/**
 * @param  list<string>  $permissionCodes
 * @return array{tenant: Tenant, branch: Branch, role: Role, user: User}
 */
function tenantWithUser(string $slug, string $username, array $permissionCodes): array
{
    $tenant = Tenant::query()->create([
        'name' => str($slug)->headline()->toString(),
        'slug' => $slug,
        'default_locale' => 'hy',
        'currency' => 'AMD',
        'status' => 'active',
    ]);

    app(TenantResolver::class)->set((int) $tenant->id);

    $branch = Branch::query()->create([
        'name' => "{$slug} Branch",
        'timezone' => 'Asia/Yerevan',
        'status' => 'active',
    ]);

    app(BranchContext::class)->set((int) $branch->id);

    $role = Role::query()->create([
        'code' => "{$slug}-manager",
        'name' => "{$slug} Manager",
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
        'default_locale' => 'hy',
        'active' => true,
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
        'role' => $role,
        'user' => $user,
    ];
}

function usesPostgresRowLevelSecurity(): bool
{
    return DB::connection()->getDriverName() === 'pgsql';
}

/**
 * @return list<int>
 */
function rawBranchIds(): array
{
    return collect(DB::select('select id from branches order by id'))
        ->map(fn (object $row): int => (int) $row->id)
        ->all();
}

/**
 * @return list<int>
 */
function rawMenuItemIds(): array
{
    return collect(DB::select('select id from menu_items order by id'))
        ->map(fn (object $row): int => (int) $row->id)
        ->all();
}
