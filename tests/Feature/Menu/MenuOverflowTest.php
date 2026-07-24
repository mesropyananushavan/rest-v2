<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Models\Permission;
use App\Modules\Identity\Infrastructure\Models\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Identity\Infrastructure\Models\UserBranchAssignment;
use App\Modules\Menu\Application\CreateMenuCategory;
use App\Modules\Menu\Application\CreateMenuItem;
use App\Modules\Menu\Infrastructure\Models\MenuCategory;
use App\Modules\Menu\Infrastructure\Models\MenuItem;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Modules\Tenancy\Infrastructure\Models\Branch;
use App\Modules\Tenancy\Infrastructure\Models\Tenant;
use App\Support\I18n\LocalizedText;
use App\Support\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

afterEach(function (): void {
    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();
});

it('renders active menu destructive actions inside row overflow menus and keeps edit visible', function (): void {
    $manager = menuOverflowUser('menu-overflow-active', 'menu-overflow-manager', ['menu.categories.manage', 'menu.items.manage']);
    $records = menuOverflowRecords($manager, 'Breakfast');

    $response = $this->actingAs($manager['user'])
        ->withSession(['branch_id' => (int) $manager['branch']->id])
        ->get(route('admin.menu.index'))
        ->assertOk()
        ->assertSee('category_overflow_'.(int) $records['category']->id, false)
        ->assertSee('item_overflow_'.(int) $records['item']->id, false)
        ->assertSee('archive_category_'.(int) $records['category']->id, false)
        ->assertSee('archive_item_'.(int) $records['item']->id, false)
        ->assertSee(__('menu.actions.edit'), false)
        ->assertSee(__('menu.actions.more'), false)
        ->assertDontSee('force_delete_item_'.(int) $records['item']->id, false)
        ->assertDontSee('force_delete_category_'.(int) $records['category']->id, false);

    $html = (string) $response->getContent();

    menuOverflowExpectContainsInOrder($html, [
        'category_overflow_'.(int) $records['category']->id,
        'role="menu"',
        'archive_category_'.(int) $records['category']->id,
    ]);
    menuOverflowExpectContainsInOrder($html, [
        'item_overflow_'.(int) $records['item']->id,
        'role="menu"',
        'archive_item_'.(int) $records['item']->id,
    ]);

    expect($html)
        ->toContain('aria-haspopup="menu"')
        ->toContain('@row-overflow-opened.window="if ($event.detail.id !== id) open = false"')
        ->toContain('@click.outside="if (open) close(false)"')
        ->toContain('@keydown.escape.prevent.stop="if (open) close(true)"')
        ->toContain('this.$refs.trigger.focus()')
        ->toContain('this.$dispatch(\'row-overflow-opened\', { id: this.id })')
        ->toContain('focusFirstItem()');
});

it('renders archived restore and force delete only inside superadmin row overflow menus', function (): void {
    $owner = menuOverflowUser(
        'menu-overflow-archived',
        'menu-overflow-owner',
        ['menu.categories.manage', 'menu.items.manage'],
        superadmin: true,
    );
    $itemRecords = menuOverflowRecords($owner, 'Item Archive');

    $this->actingAs($owner['user'])
        ->withSession(['branch_id' => (int) $owner['branch']->id])
        ->delete(route('admin.menu.items.destroy', ['item' => (int) $itemRecords['item']->id]))
        ->assertRedirect(route('admin.menu.index'));

    $itemResponse = $this->actingAs($owner['user'])
        ->withSession(['branch_id' => (int) $owner['branch']->id])
        ->get(route('admin.menu.index', ['archive_mode' => 'archived']))
        ->assertOk()
        ->assertSee('item_overflow_'.(int) $itemRecords['item']->id, false)
        ->assertSee(__('menu.actions.restore'), false)
        ->assertSee('force_delete_item_'.(int) $itemRecords['item']->id, false)
        ->assertSee(__('menu.confirm.force_delete_item_message'), false);

    menuOverflowExpectContainsInOrder((string) $itemResponse->getContent(), [
        'item_overflow_'.(int) $itemRecords['item']->id,
        'role="menu"',
        __('menu.actions.restore'),
        'force_delete_item_'.(int) $itemRecords['item']->id,
    ]);

    $categoryRecords = menuOverflowRecords($owner, 'Category Archive');

    $this->actingAs($owner['user'])
        ->withSession(['branch_id' => (int) $owner['branch']->id])
        ->delete(route('admin.menu.categories.destroy', ['category' => (int) $categoryRecords['category']->id]))
        ->assertRedirect(route('admin.menu.index'));

    $categoryResponse = $this->actingAs($owner['user'])
        ->withSession(['branch_id' => (int) $owner['branch']->id])
        ->get(route('admin.menu.index', ['archive_mode' => 'archived']))
        ->assertOk()
        ->assertSee('category_overflow_'.(int) $categoryRecords['category']->id, false)
        ->assertSee(__('menu.actions.restore'), false)
        ->assertSee('force_delete_category_'.(int) $categoryRecords['category']->id, false)
        ->assertSee(__('menu.confirm.force_delete_category_message'), false);

    menuOverflowExpectContainsInOrder((string) $categoryResponse->getContent(), [
        'category_overflow_'.(int) $categoryRecords['category']->id,
        'role="menu"',
        __('menu.actions.restore'),
        'force_delete_category_'.(int) $categoryRecords['category']->id,
    ]);
});

it('does not render archive maintenance content inside overflow menus for non-superadmins', function (): void {
    $manager = menuOverflowUser('menu-overflow-gated', 'menu-overflow-gated-manager', ['menu.categories.manage', 'menu.items.manage']);
    $records = menuOverflowRecords($manager, 'Gated Archive');

    $this->actingAs($manager['user'])
        ->withSession(['branch_id' => (int) $manager['branch']->id])
        ->delete(route('admin.menu.items.destroy', ['item' => (int) $records['item']->id]))
        ->assertRedirect(route('admin.menu.index'));

    $response = $this->actingAs($manager['user'])
        ->withSession(['branch_id' => (int) $manager['branch']->id])
        ->get(route('admin.menu.index', ['archive_mode' => 'archived']))
        ->assertOk()
        ->assertDontSee('Gated Archive Item', false)
        ->assertDontSee(__('menu.actions.restore'), false)
        ->assertDontSee(__('menu.actions.force_delete'), false)
        ->assertDontSee('force_delete_item_'.(int) $records['item']->id, false)
        ->assertDontSee('force_delete_category_'.(int) $records['category']->id, false);

    $html = (string) $response->getContent();

    expect($html)
        ->not->toContain('archiveMode\', \'archived')
        ->not->toContain('archiveMode\', \'all')
        ->not->toContain('action="'.route('admin.menu.items.restore', ['item' => (int) $records['item']->id]).'"')
        ->not->toContain('action="'.route('admin.menu.items.force-delete', ['item' => (int) $records['item']->id]).'"')
        ->not->toContain('role="menuitem">'.__('menu.actions.restore'))
        ->not->toContain(__('menu.confirm.force_delete_item_message'));
});

/**
 * @param  list<string>  $permissionCodes
 * @return array{tenant: Tenant, branch: Branch, user: User}
 */
function menuOverflowUser(string $tenantSlug, string $username, array $permissionCodes, bool $superadmin = false): array
{
    $tenant = Tenant::query()->create([
        'name' => str($tenantSlug)->headline()->toString(),
        'slug' => $tenantSlug,
        'default_locale' => 'hy',
        'currency' => 'AMD',
        'status' => 'active',
    ]);

    app(TenantResolver::class)->set((int) $tenant->id);

    $branch = Branch::query()->create([
        'name' => "{$tenantSlug} Branch",
        'timezone' => 'Asia/Yerevan',
        'status' => 'active',
    ]);

    app(BranchContext::class)->set((int) $branch->id);

    $role = Role::query()->create([
        'code' => "{$username}-role",
        'name' => "{$username} Role",
    ]);

    $permissions = collect($permissionCodes)
        ->map(fn (string $code): Permission => Permission::query()->firstOrCreate(
            ['code' => $code],
            ['name' => $code],
        ));

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
        'is_superadmin' => $superadmin,
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
        'user' => $user,
    ];
}

/**
 * @param  array{tenant: Tenant, branch: Branch, user: User}  $user
 * @return array{root: MenuCategory, category: MenuCategory, item: MenuItem}
 */
function menuOverflowRecords(array $user, string $name): array
{
    app(TenantResolver::class)->set((int) $user['tenant']->id);
    app(BranchContext::class)->set((int) $user['branch']->id);

    $root = app(CreateMenuCategory::class)(menuOverflowText("{$name} Menu"), sortOrder: 0);
    $category = app(CreateMenuCategory::class)(menuOverflowText($name), parentId: (int) $root->id);
    $item = app(CreateMenuItem::class)(
        (int) $category->id,
        menuOverflowText("{$name} Item"),
        null,
        new Money(100000, 'AMD'),
    );

    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();

    return [
        'root' => $root,
        'category' => $category,
        'item' => $item,
    ];
}

function menuOverflowText(string $text): LocalizedText
{
    return LocalizedText::fromArray([
        'hy' => $text,
        'ru' => $text,
        'en' => $text,
    ]);
}

/**
 * @param  list<string>  $needles
 */
function menuOverflowExpectContainsInOrder(string $html, array $needles): void
{
    $offset = 0;

    foreach ($needles as $needle) {
        $position = mb_strpos($html, $needle, $offset);

        expect($position)->not->toBeFalse();

        $offset = (int) $position + mb_strlen($needle);
    }
}
