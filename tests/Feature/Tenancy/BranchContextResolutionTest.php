<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Identity\Infrastructure\Models\UserBranchAssignment;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Modules\Tenancy\Infrastructure\Models\Branch;
use App\Modules\Tenancy\Infrastructure\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

afterEach(function (): void {
    app()->detectEnvironment(fn (): string => 'testing');
    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();
});

it('ignores the branch header in production', function (): void {
    $record = branchResolutionUser(branchNames: ['Production Branch']);

    app()->detectEnvironment(fn (): string => 'production');

    Route::middleware('web')->get('/_test/branch-production-context', fn (Request $request) => response()->json([
        'branch_id' => app(BranchContext::class)->id(),
        'session_branch_id' => $request->session()->get('branch_id'),
    ]));

    $this->withHeader('X-Tenant-ID', (string) $record['tenant']->id)
        ->withHeader('X-Branch-ID', (string) $record['branches'][0]->id)
        ->get('/_test/branch-production-context')
        ->assertOk()
        ->assertJson([
            'branch_id' => null,
            'session_branch_id' => null,
        ])
        ->assertSessionMissing('branch_id');
});

it('rejects an authenticated header branch that is not assigned to the user', function (): void {
    $record = branchResolutionUser(
        branchNames: ['Assigned Branch', 'Unassigned Branch'],
        assignedBranchIndexes: [0],
    );

    Route::middleware(['web', 'auth'])->get('/_test/branch-unassigned-header', fn () => response()->json([
        'branch_id' => app(BranchContext::class)->id(),
    ]));

    $this->actingAs($record['user'])
        ->withHeader('X-Branch-ID', (string) $record['branches'][1]->id)
        ->get('/_test/branch-unassigned-header')
        ->assertNotFound()
        ->assertSessionMissing('branch_id');

    expect(app(BranchContext::class)->id())->toBeNull();
});

it('rejects an authenticated header branch from another tenant', function (): void {
    $tenantA = branchResolutionUser(branchNames: ['Tenant A Branch']);
    $tenantB = branchResolutionUser(branchNames: ['Tenant B Branch']);

    Route::middleware(['web', 'auth'])->get('/_test/branch-foreign-header', fn () => response()->json([
        'branch_id' => app(BranchContext::class)->id(),
    ]));

    $this->actingAs($tenantA['user'])
        ->withHeader('X-Branch-ID', (string) $tenantB['branches'][0]->id)
        ->get('/_test/branch-foreign-header')
        ->assertNotFound()
        ->assertSessionMissing('branch_id');

    expect(app(BranchContext::class)->id())->toBeNull();
});

it('discards an unassigned session branch and falls back to the first assigned branch', function (): void {
    $record = branchResolutionUser(
        branchNames: ['Assigned Branch', 'Stale Session Branch'],
        assignedBranchIndexes: [0],
    );

    Log::spy();

    Route::middleware(['web', 'auth'])->get('/_test/branch-stale-session', fn (Request $request) => response()->json([
        'branch_id' => app(BranchContext::class)->id(),
        'session_branch_id' => $request->session()->get('branch_id'),
    ]));

    $this->actingAs($record['user'])
        ->withSession(['branch_id' => (int) $record['branches'][1]->id])
        ->get('/_test/branch-stale-session')
        ->assertOk()
        ->assertJson([
            'branch_id' => (int) $record['branches'][0]->id,
            'session_branch_id' => (int) $record['branches'][0]->id,
        ])
        ->assertSessionHas('branch_id', (int) $record['branches'][0]->id);

    Log::shouldHaveReceived('warning')
        ->with('branch candidate rejected', [
            'source' => 'session',
            'reason_code' => 'branch_not_assigned',
        ])
        ->once();
});

it('resolves an authenticated header branch that is assigned to the user', function (): void {
    $record = branchResolutionUser(
        branchNames: ['First Branch', 'Assigned Header Branch'],
        assignedBranchIndexes: [0, 1],
    );

    Route::middleware(['web', 'auth'])->get('/_test/branch-assigned-header', fn (Request $request) => response()->json([
        'branch_id' => app(BranchContext::class)->id(),
        'session_branch_id' => $request->session()->get('branch_id'),
    ]));

    $this->actingAs($record['user'])
        ->withSession(['branch_id' => (int) $record['branches'][0]->id])
        ->withHeader('X-Branch-ID', (string) $record['branches'][1]->id)
        ->get('/_test/branch-assigned-header')
        ->assertOk()
        ->assertJson([
            'branch_id' => (int) $record['branches'][1]->id,
            'session_branch_id' => (int) $record['branches'][1]->id,
        ])
        ->assertSessionHas('branch_id', (int) $record['branches'][1]->id);
});

/**
 * @param  list<string>  $branchNames
 * @param  list<int>  $assignedBranchIndexes
 * @return array{tenant: Tenant, branches: list<Branch>, user: User}
 */
function branchResolutionUser(array $branchNames, array $assignedBranchIndexes = [0]): array
{
    static $sequence = 0;

    $sequence++;

    $tenant = Tenant::query()->create([
        'name' => "Branch Resolution Tenant {$sequence}",
        'slug' => "branch-resolution-{$sequence}",
        'default_locale' => 'hy',
        'currency' => 'AMD',
        'status' => 'active',
    ]);

    app(TenantResolver::class)->set((int) $tenant->id);

    $branches = [];

    foreach ($branchNames as $branchName) {
        $branches[] = Branch::query()->create([
            'name' => $branchName,
            'timezone' => 'Asia/Yerevan',
            'status' => 'active',
        ]);
    }

    $user = User::query()->create([
        'name' => "Branch Resolution User {$sequence}",
        'email' => "branch-resolution-{$sequence}@smartrest.test",
        'username' => "branch-resolution-{$sequence}",
        'default_locale' => 'hy',
        'active' => true,
        'password' => Hash::make('password'),
    ]);

    foreach ($assignedBranchIndexes as $branchIndex) {
        UserBranchAssignment::query()->create([
            'user_id' => (int) $user->id,
            'branch_id' => (int) $branches[$branchIndex]->id,
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
