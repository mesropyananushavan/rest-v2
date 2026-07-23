<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Models\Permission;
use App\Modules\Identity\Infrastructure\Models\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Identity\Infrastructure\Models\UserBranchAssignment;
use App\Modules\Tables\Application\ArchiveHall;
use App\Modules\Tables\Application\ArchiveTable;
use App\Modules\Tables\Application\CreateHall;
use App\Modules\Tables\Application\CreateTable;
use App\Modules\Tables\Application\ForceDeleteHall;
use App\Modules\Tables\Application\ForceDeleteTable;
use App\Modules\Tables\Application\PaginateTables;
use App\Modules\Tables\Application\RestoreHall;
use App\Modules\Tables\Application\RestoreTable;
use App\Modules\Tables\Application\UpdateTable;
use App\Modules\Tables\Domain\TablesDomainException;
use App\Modules\Tables\Infrastructure\Models\Hall;
use App\Modules\Tables\Infrastructure\Models\Table;
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

it('creates updates archives restores and permanently deletes tables with audit rows', function (): void {
    $record = tablesActionsUser('tenant-a', 'manager-a', ['tables.halls.manage', 'tables.tables.manage'], superadmin: true);

    tablesActionsActingIn($record, 0, 'tables-actions-request');

    $hall = app(CreateHall::class)(tablesActionsText('Main Hall'), '#5FA8D3', sortOrder: 10);
    $table = app(CreateTable::class)((int) $hall->id, tablesActionsText('Table 1'), type: 'standard', shape: 'square', hdmDepartment: 1, sortOrder: 10);

    $createdAudit = AuditLog::query()->where('action', 'tables.table.created')->firstOrFail();

    expect((int) $table->branch_id)->toBe((int) $record['branches'][0]->id)
        ->and((int) $table->hall_id)->toBe((int) $hall->id)
        ->and($createdAudit->tenant_id)->toBe((int) $record['tenant']->id)
        ->and($createdAudit->branch_id)->toBe((int) $record['branches'][0]->id)
        ->and($createdAudit->actor_id)->toBe((int) $record['user']->id)
        ->and($createdAudit->target_type)->toBe('tables_table')
        ->and($createdAudit->target_id)->toBe((int) $table->id)
        ->and($createdAudit->correlation_id)->toBe('tables-actions-request')
        ->and($createdAudit->before_json)->toBeNull()
        ->and($createdAudit->after_json['translated_name']['en'])->toBe('Table 1')
        ->and($createdAudit->after_json['shape'])->toBe('square')
        ->and($createdAudit->after_json['hdm_department'])->toBe(1);

    app(UpdateTable::class)((int) $hall->id, (int) $table->id, tablesActionsText('VIP'), 'vip', 'rectangle', 2, true, 20, false);
    app(ArchiveTable::class)((int) $hall->id, (int) $table->id);
    app(RestoreTable::class)((int) $hall->id, (int) $table->id);
    app(ArchiveTable::class)((int) $hall->id, (int) $table->id);
    app(ForceDeleteTable::class)((int) $hall->id, (int) $table->id);

    expect(AuditLog::query()->where('target_type', 'tables_table')->orderBy('id')->pluck('action')->all())->toBe([
        'tables.table.created',
        'tables.table.updated',
        'tables.table.archived',
        'tables.table.restored',
        'tables.table.archived',
        'tables.table.permanently_deleted',
    ])->and(Table::withTrashed()->find((int) $table->id))->toBeNull();

    $deleteAudit = AuditLog::query()->where('action', 'tables.table.permanently_deleted')->firstOrFail();

    expect($deleteAudit->before_json['translated_name']['en'])->toBe('VIP')
        ->and($deleteAudit->after_json)->toBe(['deleted' => true]);
});

it('rolls table audit rows back with the failed enclosing transaction', function (): void {
    $record = tablesActionsUser('tenant-a', 'manager-a', ['tables.halls.manage', 'tables.tables.manage']);

    tablesActionsActingIn($record, 0, 'tables-rollback-request');
    $hall = app(CreateHall::class)(tablesActionsText('Rollback Hall'), '#5FA8D3');
    $auditCountBefore = AuditLog::query()->count();

    try {
        DB::transaction(function () use ($hall): void {
            app(CreateTable::class)((int) $hall->id, tablesActionsText('Rollback Table'));

            throw new RuntimeException('rollback');
        });
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('rollback');
    }

    expect(Table::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe($auditCountBefore);
});

it('paginates tables only inside the selected branch and hall', function (): void {
    $record = tablesActionsUser('tenant-a', 'manager-a', ['tables.halls.manage', 'tables.tables.manage'], branchCount: 2);

    tablesActionsActingIn($record, 0, 'branch-a-request');
    $visibleHall = app(CreateHall::class)(tablesActionsText('Visible Hall'), '#5FA8D3');
    $otherHall = app(CreateHall::class)(tablesActionsText('Other Hall'), '#D36B5F');
    $visible = app(CreateTable::class)((int) $visibleHall->id, tablesActionsText('Visible Table'));
    app(CreateTable::class)((int) $otherHall->id, tablesActionsText('Hidden Hall Table'));

    tablesActionsActingIn($record, 1, 'branch-b-request');
    $foreignBranchHall = app(CreateHall::class)(tablesActionsText('Foreign Branch Hall'), '#78CD51');
    app(CreateTable::class)((int) $foreignBranchHall->id, tablesActionsText('Hidden Branch Table'));

    tablesActionsActingIn($record, 0, 'branch-a-request');

    $tables = app(PaginateTables::class)((int) $visibleHall->id, includeInactive: true, perPage: 25);

    expect($tables->total())->toBe(1)
        ->and($tables->items()[0]->id)->toBe((int) $visible->id);
});

it('cascades hall archive restore and force delete through explicit table markers', function (): void {
    $record = tablesActionsUser('tenant-a', 'manager-a', ['tables.halls.manage', 'tables.tables.manage'], superadmin: true);

    tablesActionsActingIn($record, 0, 'tables-cascade-request');

    $hall = app(CreateHall::class)(tablesActionsText('Cascade Hall'), '#5FA8D3');
    $cascadeTable = app(CreateTable::class)((int) $hall->id, tablesActionsText('Cascade Table'), sortOrder: 10);
    $independentTable = app(CreateTable::class)((int) $hall->id, tablesActionsText('Independent Table'), sortOrder: 20);
    $forceDeleteTable = app(CreateTable::class)((int) $hall->id, tablesActionsText('Force Delete Table'), sortOrder: 30);

    app(ArchiveTable::class)((int) $hall->id, (int) $independentTable->id);
    app(ArchiveHall::class)((int) $hall->id);

    $cascadeTable = Table::withTrashed()->findOrFail((int) $cascadeTable->id);
    $independentTable = Table::withTrashed()->findOrFail((int) $independentTable->id);

    expect($cascadeTable->trashed())->toBeTrue()
        ->and($cascadeTable->archived_with_hall_id)->toBe((int) $hall->id)
        ->and($independentTable->trashed())->toBeTrue()
        ->and($independentTable->archived_with_hall_id)->toBeNull();

    $archiveAudit = AuditLog::query()
        ->where('action', 'tables.hall.archived')
        ->latest('id')
        ->firstOrFail();

    expect($archiveAudit->after_json['cascade'])->toBe([
        'archived_table_count' => 2,
        'marker_hall_id' => (int) $hall->id,
    ])->and(AuditLog::query()->where('action', 'tables.table.archived')->count())->toBe(1);

    app(RestoreHall::class)((int) $hall->id);

    $cascadeTable = Table::query()->findOrFail((int) $cascadeTable->id);
    $independentTable = Table::withTrashed()->findOrFail((int) $independentTable->id);

    expect($cascadeTable->trashed())->toBeFalse()
        ->and($cascadeTable->archived_with_hall_id)->toBeNull()
        ->and($independentTable->trashed())->toBeTrue()
        ->and($independentTable->archived_with_hall_id)->toBeNull();

    $restoreAudit = AuditLog::query()
        ->where('action', 'tables.hall.restored')
        ->latest('id')
        ->firstOrFail();

    expect($restoreAudit->after_json['cascade'])->toBe([
        'marker_hall_id' => (int) $hall->id,
        'restored_table_count' => 2,
    ]);

    expect(fn () => app(RestoreTable::class)((int) $hall->id, (int) $independentTable->id))->not->toThrow(Exception::class);

    app(ArchiveHall::class)((int) $hall->id);
    app(ForceDeleteHall::class)((int) $hall->id);

    expect(Hall::withTrashed()->find((int) $hall->id))->toBeNull()
        ->and(Table::withTrashed()->find((int) $cascadeTable->id))->toBeNull()
        ->and(Table::withTrashed()->find((int) $independentTable->id))->toBeNull()
        ->and(Table::withTrashed()->find((int) $forceDeleteTable->id))->toBeNull();

    $forceDeleteAudit = AuditLog::query()
        ->where('action', 'tables.hall.permanently_deleted')
        ->latest('id')
        ->firstOrFail();

    expect($forceDeleteAudit->after_json['cascade'])->toBe([
        'deleted_table_count' => 3,
    ]);
});

it('blocks restoring an independently archived table while its hall is archived', function (): void {
    $record = tablesActionsUser('tenant-a', 'manager-a', ['tables.halls.manage', 'tables.tables.manage'], superadmin: true);

    tablesActionsActingIn($record, 0, 'table-restore-parent-request');

    $hall = app(CreateHall::class)(tablesActionsText('Archived Hall'), '#5FA8D3');
    $table = app(CreateTable::class)((int) $hall->id, tablesActionsText('Archived Table'));

    app(ArchiveTable::class)((int) $hall->id, (int) $table->id);
    app(ArchiveHall::class)((int) $hall->id);

    expect(fn () => app(RestoreTable::class)((int) $hall->id, (int) $table->id))
        ->toThrow(TablesDomainException::class, 'Restore the hall before restoring this table.');
});

/**
 * @param  list<string>  $permissionCodes
 * @return array{tenant: Tenant, branches: list<Branch>, user: User}
 */
function tablesActionsUser(string $tenantSlug, string $username, array $permissionCodes, int $branchCount = 1, bool $superadmin = false): array
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
function tablesActionsActingIn(array $record, int $branchIndex, string $requestId): void
{
    app(TenantResolver::class)->set((int) $record['tenant']->id);
    app(BranchContext::class)->set((int) $record['branches'][$branchIndex]->id);
    auth()->login($record['user']);
    LogContext::start($requestId, 'tables');
}

function tablesActionsText(string $text): LocalizedText
{
    return LocalizedText::fromArray([
        'hy' => $text,
        'ru' => $text,
        'en' => $text,
    ]);
}
