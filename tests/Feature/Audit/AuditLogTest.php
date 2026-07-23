<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Menu\Application\ArchiveMenuCategory;
use App\Modules\Menu\Application\ArchiveMenuItem;
use App\Modules\Menu\Application\CreateMenuCategory;
use App\Modules\Menu\Application\CreateMenuItem;
use App\Modules\Menu\Application\ForceDeleteMenuCategory;
use App\Modules\Menu\Application\ForceDeleteMenuItem;
use App\Modules\Menu\Application\RemoveMenuItemImage;
use App\Modules\Menu\Application\ReplaceMenuItemImage;
use App\Modules\Menu\Application\RestoreMenuCategory;
use App\Modules\Menu\Application\RestoreMenuItem;
use App\Modules\Menu\Application\ToggleMenuItemActivity;
use App\Modules\Menu\Application\UpdateMenuCategory;
use App\Modules\Menu\Application\UpdateMenuItem;
use App\Modules\Menu\Domain\MenuItemImageSlot;
use App\Modules\Menu\Infrastructure\Models\MenuCategory;
use App\Modules\Menu\Infrastructure\Models\MenuItem;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Modules\Tenancy\Infrastructure\Models\Branch;
use App\Modules\Tenancy\Infrastructure\Models\Tenant;
use App\Support\Audit\AuditLog;
use App\Support\Audit\AuditRecorder;
use App\Support\I18n\LocalizedText;
use App\Support\Logging\LogContext;
use App\Support\Money\Money;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

afterEach(function (): void {
    Auth::logout();
    LogContext::clear();
    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();
});

it('records a menu mutation with actor tenant branch correlation target and before after content', function (): void {
    $record = auditLogTenant('audit-a', 'Audit A');
    $item = auditLogItem($record['tenant'], $record['branch'], active: true);

    Route::middleware(['web', 'tenant', 'branch', 'auth'])->post(
        '/_test/audit/items/{item}/toggle',
        function (int $item): Response {
            app(ToggleMenuItemActivity::class)($item);

            return response()->noContent();
        },
    );

    $this->actingAs($record['user'])
        ->withSession(['branch_id' => (int) $record['branch']->id])
        ->withHeader('X-Request-Id', 'audit-request-1')
        ->post("/_test/audit/items/{$item->id}/toggle")
        ->assertNoContent()
        ->assertHeader('X-Request-Id', 'audit-request-1');

    app(TenantResolver::class)->set((int) $record['tenant']->id);

    $audit = AuditLog::query()->sole();

    expect((int) $audit->tenant_id)->toBe((int) $record['tenant']->id)
        ->and((int) $audit->branch_id)->toBe((int) $record['branch']->id)
        ->and((int) $audit->actor_id)->toBe((int) $record['user']->id)
        ->and($audit->action)->toBe('menu.item.activity_toggled')
        ->and($audit->target_type)->toBe('menu_item')
        ->and((int) $audit->target_id)->toBe((int) $item->id)
        ->and($audit->correlation_id)->toBe('audit-request-1')
        ->and($audit->before_json['active'])->toBeTrue()
        ->and($audit->after_json['active'])->toBeFalse();
});

it('rolls back the menu mutation when audit recording fails', function (): void {
    $record = auditLogTenant('audit-rollback', 'Audit Rollback');

    app(TenantResolver::class)->set((int) $record['tenant']->id);
    LogContext::start('audit-rollback-request', 'menu');

    app()->instance(AuditRecorder::class, new class implements AuditRecorder
    {
        public function record(
            string $action,
            string $targetType,
            int $targetId,
            ?array $before = null,
            ?array $after = null,
        ): AuditLog {
            throw new RuntimeException('audit failed');
        }
    });

    expect(fn () => app(CreateMenuCategory::class)(auditLogText('Rollback category')))
        ->toThrow(RuntimeException::class, 'audit failed');

    expect(MenuCategory::query()->count())->toBe(0);
});

it('keeps audit writes transactional with rollback and commit boundaries', function (): void {
    $record = auditLogTenant('audit-transaction', 'Audit Transaction');

    app(TenantResolver::class)->set((int) $record['tenant']->id);
    LogContext::start('audit-transaction-request', 'menu');

    try {
        DB::transaction(function (): void {
            $category = MenuCategory::query()->create([
                'translated_name' => auditLogText('Rolled Back')->toArray(),
                'sort_order' => 0,
                'active' => true,
            ]);

            app(AuditRecorder::class)->record('menu.category.created', 'menu_category', (int) $category->id, null, [
                'name' => 'Rolled Back',
            ]);

            throw new RuntimeException('rollback requested');
        });
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('rollback requested');
    }

    expect(AuditLog::query()->count())->toBe(0);

    $category = app(CreateMenuCategory::class)(auditLogText('Committed'));

    expect(AuditLog::query()->where('target_id', (int) $category->id)->where('action', 'menu.category.created')->count())
        ->toBe(1);
});

it('prevents audit log updates deletes soft deletes and raw table mutation', function (): void {
    $record = auditLogTenant('audit-append-only', 'Audit Append Only');

    app(TenantResolver::class)->set((int) $record['tenant']->id);
    LogContext::start('audit-append-only-request', 'menu');

    $audit = app(AuditRecorder::class)->record('menu.category.created', 'menu_category', 123, null, [
        'name' => 'Append Only',
    ]);

    expect(class_uses_recursive(AuditLog::class))->not->toContain(SoftDeletes::class)
        ->and(fn () => $audit->update(['action' => 'compromised']))
        ->toThrow(LogicException::class, 'Audit logs are append-only and cannot be updated.')
        ->and(fn () => $audit->delete())
        ->toThrow(LogicException::class, 'Audit logs are append-only and cannot be deleted.')
        ->and(fn () => DB::table('audit_logs')->where('id', (int) $audit->id)->update(['action' => 'compromised']))
        ->toThrow(QueryException::class)
        ->and(fn () => DB::table('audit_logs')->where('id', (int) $audit->id)->delete())
        ->toThrow(QueryException::class);
});

it('redacts sensitive values before storing audit json', function (): void {
    $record = auditLogTenant('audit-redaction', 'Audit Redaction');

    app(TenantResolver::class)->set((int) $record['tenant']->id);
    LogContext::start('audit-redaction-request', 'menu');

    $audit = app(AuditRecorder::class)->record('menu.item.updated', 'menu_item', 456, [
        'password' => 'secret-password',
    ], [
        'nested' => [
            'api_token' => 'secret-token',
        ],
        'translated_name' => ['en' => 'Safe name'],
    ]);

    expect($audit->before_json['password'])->toBe('[redacted]')
        ->and($audit->after_json['nested']['api_token'])->toBe('[redacted]')
        ->and($audit->after_json['translated_name']['en'])->toBe('Safe name');
});

it('tenant scopes audit rows through Eloquent', function (): void {
    $tenantA = auditLogTenant('audit-tenant-a', 'Audit Tenant A');
    $tenantB = auditLogTenant('audit-tenant-b', 'Audit Tenant B');

    app(TenantResolver::class)->set((int) $tenantA['tenant']->id);
    LogContext::start('audit-tenant-a-request', 'menu');
    app(AuditRecorder::class)->record('menu.category.created', 'menu_category', 1, null, ['tenant' => 'a']);

    app(TenantResolver::class)->set((int) $tenantB['tenant']->id);
    LogContext::start('audit-tenant-b-request', 'menu');
    app(AuditRecorder::class)->record('menu.category.created', 'menu_category', 2, null, ['tenant' => 'b']);

    app(TenantResolver::class)->set((int) $tenantA['tenant']->id);

    expect(AuditLog::query()->pluck('target_id')->all())->toBe([1])
        ->and(AuditLog::query()->where('target_id', 2)->first())->toBeNull();
});

it('records the full Menu mutating action audit string set', function (): void {
    Storage::fake('public');

    $record = auditLogTenant('audit-actions', 'Audit Actions');

    Auth::login($record['user']);
    app(TenantResolver::class)->set((int) $record['tenant']->id);
    app(BranchContext::class)->set((int) $record['branch']->id);
    LogContext::start('audit-actions-request', 'menu');

    $root = app(CreateMenuCategory::class)(auditLogText('Root'));
    $category = app(CreateMenuCategory::class)(auditLogText('Breakfast'), parentId: (int) $root->id);
    $category = app(UpdateMenuCategory::class)((int) $category->id, auditLogText('Morning'), 5, true, (int) $root->id);
    $item = app(CreateMenuItem::class)((int) $category->id, auditLogText('Omelette'), null, new Money(180000, 'AMD'));
    $item = app(UpdateMenuItem::class)((int) $item->id, (int) $category->id, auditLogText('Cheese Omelette'), null, new Money(210000, 'AMD'), 1, true);
    app(ToggleMenuItemActivity::class)((int) $item->id);
    $item = app(ReplaceMenuItemImage::class)((int) $item->id, MenuItemImageSlot::Internal, UploadedFile::fake()->image('internal.jpg', 400, 300)->size(128));
    app(RemoveMenuItemImage::class)((int) $item->id, MenuItemImageSlot::Internal);
    app(ArchiveMenuItem::class)((int) $item->id);
    app(RestoreMenuItem::class)((int) $item->id);
    app(ArchiveMenuItem::class)((int) $item->id);
    app(ForceDeleteMenuItem::class)((int) $item->id);
    app(ArchiveMenuCategory::class)((int) $category->id);
    app(RestoreMenuCategory::class)((int) $category->id);
    app(ArchiveMenuCategory::class)((int) $category->id);
    app(ForceDeleteMenuCategory::class)((int) $category->id);

    expect(AuditLog::query()->pluck('action')->unique()->sort()->values()->all())->toBe([
        'menu.category.archived',
        'menu.category.created',
        'menu.category.permanently_deleted',
        'menu.category.restored',
        'menu.category.updated',
        'menu.item.activity_toggled',
        'menu.item.archived',
        'menu.item.created',
        'menu.item.image_removed',
        'menu.item.image_replaced',
        'menu.item.permanently_deleted',
        'menu.item.restored',
        'menu.item.updated',
    ]);

    $categoryArchive = AuditLog::query()
        ->where('action', 'menu.category.archived')
        ->latest('id')
        ->firstOrFail();

    expect($categoryArchive->after_json['cascade']['category_level'])->toBe('subcategory')
        ->and($categoryArchive->after_json['cascade']['archived_item_count'])->toBe(0)
        ->and($categoryArchive->after_json['cascade']['marker_category_id'])->toBe((int) $category->id);
});

/**
 * @return array{tenant: Tenant, branch: Branch, user: User}
 */
function auditLogTenant(string $slug, string $name): array
{
    $tenant = Tenant::query()->create([
        'name' => $name,
        'slug' => $slug,
        'default_locale' => 'hy',
        'currency' => 'AMD',
        'status' => 'active',
    ]);

    app(TenantResolver::class)->set((int) $tenant->id);

    $branch = Branch::query()->create([
        'name' => "{$name} Branch",
        'timezone' => 'Asia/Yerevan',
        'status' => 'active',
    ]);

    app(BranchContext::class)->set((int) $branch->id);

    $user = User::query()->create([
        'name' => "{$name} Manager",
        'email' => "{$slug}@smartrest.test",
        'username' => "{$slug}-manager",
        'default_locale' => 'hy',
        'active' => true,
        'password' => Hash::make('password'),
    ]);

    return [
        'tenant' => $tenant,
        'branch' => $branch,
        'user' => $user,
    ];
}

function auditLogText(string $text): LocalizedText
{
    return LocalizedText::fromArray([
        'hy' => $text,
        'ru' => $text,
        'en' => $text,
    ]);
}

function auditLogItem(Tenant $tenant, Branch $branch, bool $active): MenuItem
{
    app(TenantResolver::class)->set((int) $tenant->id);
    app(BranchContext::class)->set((int) $branch->id);

    $root = MenuCategory::query()->create([
        'translated_name' => auditLogText('Root')->toArray(),
        'sort_order' => 0,
        'active' => true,
    ]);

    $category = MenuCategory::query()->create([
        'parent_id' => (int) $root->id,
        'translated_name' => auditLogText('Category')->toArray(),
        'sort_order' => 0,
        'active' => true,
    ]);

    return MenuItem::query()->create([
        'branch_id' => (int) $branch->id,
        'category_id' => (int) $category->id,
        'translated_name' => auditLogText('Item')->toArray(),
        'translated_description' => null,
        'price_minor' => 100000,
        'currency' => 'AMD',
        'sort_order' => 0,
        'active' => $active,
    ]);
}
