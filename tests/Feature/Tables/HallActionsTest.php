<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Models\Permission;
use App\Modules\Identity\Infrastructure\Models\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Identity\Infrastructure\Models\UserBranchAssignment;
use App\Modules\Tables\Application\ArchiveHall;
use App\Modules\Tables\Application\CreateHall;
use App\Modules\Tables\Application\ForceDeleteHall;
use App\Modules\Tables\Application\PaginateHalls;
use App\Modules\Tables\Application\RestoreHall;
use App\Modules\Tables\Application\UpdateHall;
use App\Modules\Tables\Infrastructure\Models\Hall;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Modules\Tenancy\Infrastructure\Models\Branch;
use App\Modules\Tenancy\Infrastructure\Models\Tenant;
use App\Support\Audit\AuditLog;
use App\Support\I18n\LocalizedText;
use App\Support\Logging\LogContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

afterEach(function (): void {
    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();
    LogContext::clear();
});

it('creates updates archives restores and permanently deletes halls with audit rows', function (): void {
    $record = hallsActionsUser('tenant-a', 'manager-a', ['tables.halls.manage'], branchCount: 1, superadmin: true);

    hallsActionsActingIn($record, 0, 'halls-actions-request');

    $hall = app(CreateHall::class)(hallsActionsText('Main Hall'), '#5FA8D3', sortOrder: 10);

    $createdAudit = AuditLog::query()->where('action', 'tables.hall.created')->firstOrFail();

    expect((int) $hall->branch_id)->toBe((int) $record['branches'][0]->id)
        ->and($createdAudit->tenant_id)->toBe((int) $record['tenant']->id)
        ->and($createdAudit->branch_id)->toBe((int) $record['branches'][0]->id)
        ->and($createdAudit->actor_id)->toBe((int) $record['user']->id)
        ->and($createdAudit->target_type)->toBe('tables_hall')
        ->and($createdAudit->target_id)->toBe((int) $hall->id)
        ->and($createdAudit->correlation_id)->toBe('halls-actions-request')
        ->and($createdAudit->before_json)->toBeNull()
        ->and($createdAudit->after_json['translated_name']['en'])->toBe('Main Hall')
        ->and($createdAudit->after_json['color'])->toBe('#5FA8D3');

    app(UpdateHall::class)((int) $hall->id, hallsActionsText('Main Room'), '#D36B5F', 20, false);
    app(ArchiveHall::class)((int) $hall->id);
    app(RestoreHall::class)((int) $hall->id);
    app(ArchiveHall::class)((int) $hall->id);
    app(ForceDeleteHall::class)((int) $hall->id);

    expect(AuditLog::query()->orderBy('id')->pluck('action')->all())->toBe([
        'tables.hall.created',
        'tables.hall.updated',
        'tables.hall.archived',
        'tables.hall.restored',
        'tables.hall.archived',
        'tables.hall.permanently_deleted',
    ])->and(Hall::withTrashed()->find((int) $hall->id))->toBeNull();

    $deleteAudit = AuditLog::query()->where('action', 'tables.hall.permanently_deleted')->firstOrFail();

    expect($deleteAudit->before_json['translated_name']['en'])->toBe('Main Room')
        ->and($deleteAudit->after_json)->toBe([
            'deleted' => true,
            'cascade' => [
                'deleted_table_count' => 0,
            ],
        ]);
});

it('rolls hall audit rows back with the failed enclosing transaction', function (): void {
    $record = hallsActionsUser('tenant-a', 'manager-a', ['tables.halls.manage']);

    hallsActionsActingIn($record, 0, 'halls-rollback-request');

    try {
        DB::transaction(function (): void {
            app(CreateHall::class)(hallsActionsText('Rollback Hall'), '#5FA8D3');

            throw new RuntimeException('rollback');
        });
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('rollback');
    }

    expect(Hall::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0);
});

it('paginates halls only inside the selected branch', function (): void {
    $record = hallsActionsUser('tenant-a', 'manager-a', ['tables.halls.manage'], branchCount: 2);

    hallsActionsActingIn($record, 0, 'branch-a-request');
    $visible = app(CreateHall::class)(hallsActionsText('Visible Hall'), '#5FA8D3');

    hallsActionsActingIn($record, 1, 'branch-b-request');
    app(CreateHall::class)(hallsActionsText('Hidden Branch Hall'), '#D36B5F');

    hallsActionsActingIn($record, 0, 'branch-a-request');

    $halls = app(PaginateHalls::class)(includeInactive: true, perPage: 25);

    expect($halls->total())->toBe(1)
        ->and($halls->items()[0]->id)->toBe((int) $visible->id);
});

/**
 * @param  list<string>  $permissionCodes
 * @return array{tenant: Tenant, branches: list<Branch>, user: User}
 */
function hallsActionsUser(string $tenantSlug, string $username, array $permissionCodes, int $branchCount = 1, bool $superadmin = false): array
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
        'branches' => $branches,
        'user' => $user,
    ];
}

/**
 * @param  array{tenant: Tenant, branches: list<Branch>, user: User}  $record
 */
function hallsActionsActingIn(array $record, int $branchIndex, string $requestId): void
{
    app(TenantResolver::class)->set((int) $record['tenant']->id);
    app(BranchContext::class)->set((int) $record['branches'][$branchIndex]->id);
    auth()->login($record['user']);
    LogContext::start($requestId, 'tables');
}

function hallsActionsText(string $text): LocalizedText
{
    return LocalizedText::fromArray([
        'hy' => $text,
        'ru' => $text,
        'en' => $text,
    ]);
}
