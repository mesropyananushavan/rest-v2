<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Models\Permission;
use App\Modules\Identity\Infrastructure\Models\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Identity\Infrastructure\Models\UserBranchAssignment;
use App\Modules\Menu\Application\CreateMenuCategory;
use App\Modules\Menu\Infrastructure\Models\MenuCategory;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Modules\Tenancy\Infrastructure\Models\Branch;
use App\Modules\Tenancy\Infrastructure\Models\Tenant;
use App\Support\I18n\LocalizedText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

uses(RefreshDatabase::class);

afterEach(function (): void {
    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();
});

it('never uses hostile menu context values as redirect targets', function (): void {
    $records = menuContextSecurityRecords();
    $categoryId = (int) $records['category']->id;

    $cases = [
        'absolute external URL in a known context value' => [
            'context' => [
                'category' => $categoryId,
                'q' => 'https://evil.example/phish',
            ],
            'expected' => [
                'category' => $categoryId,
                'q' => 'https://evil.example/phish',
            ],
            'forbidden' => ['https://evil.example/phish'],
        ],
        'protocol-relative value in a known context value' => [
            'context' => [
                'category' => $categoryId,
                'q' => '//evil.example/phish',
            ],
            'expected' => [
                'category' => $categoryId,
                'q' => '//evil.example/phish',
            ],
            'forbidden' => ['//evil.example/phish'],
        ],
        'path pointing at another admin route in a known context value' => [
            'context' => [
                'category' => $categoryId,
                'q' => '/admin/tables/halls',
            ],
            'expected' => [
                'category' => $categoryId,
                'q' => '/admin/tables/halls',
            ],
            'forbidden' => ['/admin/tables/halls'],
        ],
        'encoded newline separator smuggling in a known context value' => [
            'context' => [
                'category' => $categoryId,
                'q' => "needle%0d%0aLocation:%20https://evil.example/phish\nSet-Cookie: bad=1",
            ],
            'expected' => [
                'category' => $categoryId,
                'q' => "needle%0d%0aLocation:%20https://evil.example/phish\nSet-Cookie: bad=1",
            ],
            'forbidden' => ["\nSet-Cookie", '%0d%0aLocation:', 'https://evil.example/phish'],
        ],
        'unexpected extra context keys' => [
            'context' => [
                'category' => $categoryId,
                'redirect' => 'https://evil.example/phish',
                'return_url' => '/admin/tables/halls',
                'next' => '//evil.example/phish',
            ],
            'expected' => [
                'category' => $categoryId,
            ],
            'forbidden' => ['https://evil.example/phish', '/admin/tables/halls', '//evil.example/phish'],
        ],
        'scalar and array type confusion' => [
            'context' => [
                'category' => ['https://evil.example/phish'],
                'q' => ['//evil.example/phish'],
                'item_page' => ['2'],
                'archive_mode' => ['all'],
                'show_inactive' => ['1'],
            ],
            'expected' => [],
            'forbidden' => ['https://evil.example/phish', '//evil.example/phish'],
        ],
    ];

    foreach ($cases as $label => $case) {
        /** @var array<string, mixed> $context */
        $context = $case['context'];
        /** @var array<string, int|string> $expected */
        $expected = $case['expected'];
        /** @var list<string> $forbidden */
        $forbidden = $case['forbidden'];
        $expectedReturnUrl = route('admin.menu.index', $expected);

        $createResponse = $this->actingAs($records['user'])
            ->withSession(['branch_id' => (int) $records['branch']->id])
            ->get(route('admin.menu.categories.create', ['context' => $context]));

        $createResponse
            ->assertOk()
            ->assertSee($expectedReturnUrl);

        expect(parse_url($expectedReturnUrl, PHP_URL_PATH))->toBe('/admin/menu')
            ->and(parse_url($expectedReturnUrl, PHP_URL_HOST))->not->toBe('evil.example');

        $this->actingAs($records['user'])
            ->withSession(['branch_id' => (int) $records['branch']->id])
            ->get($expectedReturnUrl)
            ->assertOk();

        $storeResponse = $this->actingAs($records['user'])
            ->withSession(['branch_id' => (int) $records['branch']->id])
            ->post(route('admin.menu.categories.store'), menuContextSecurityCategoryPayload("Secure {$label}", $context));

        $location = (string) $storeResponse->headers->get('Location');

        $storeResponse
            ->assertStatus(Response::HTTP_FOUND)
            ->assertRedirect($expectedReturnUrl);

        expect(parse_url($location, PHP_URL_PATH))->toBe('/admin/menu')
            ->and(parse_url($location, PHP_URL_HOST))->not->toBe('evil.example');

        foreach ($forbidden as $target) {
            expect($location)->not->toContain($target);
        }
    }
});

/**
 * @return array{tenant: Tenant, branch: Branch, user: User, root: MenuCategory, category: MenuCategory}
 */
function menuContextSecurityRecords(): array
{
    $tenant = Tenant::query()->create([
        'name' => 'Menu Context Security',
        'slug' => 'menu-context-security',
        'default_locale' => 'hy',
        'currency' => 'AMD',
        'status' => 'active',
    ]);

    app(TenantResolver::class)->set((int) $tenant->id);

    $branch = Branch::query()->create([
        'name' => 'Menu Context Security Branch',
        'timezone' => 'Asia/Yerevan',
        'status' => 'active',
    ]);

    app(BranchContext::class)->set((int) $branch->id);

    $root = app(CreateMenuCategory::class)(menuContextSecurityText('Menu'), sortOrder: 0);
    $category = app(CreateMenuCategory::class)(menuContextSecurityText('Breakfast'), sortOrder: 10, parentId: (int) $root->id);

    $role = Role::query()->create([
        'code' => 'menu-context-security-role',
        'name' => 'Menu Context Security Role',
    ]);

    $permissions = collect(['menu.categories.manage', 'menu.items.manage'])
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
        'name' => 'Menu Context Security Manager',
        'email' => 'menu-context-security@smartrest.test',
        'username' => 'menu-context-security-manager',
        'default_locale' => 'hy',
        'active' => true,
        'is_superadmin' => false,
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
        'root' => $root,
        'category' => $category,
    ];
}

/**
 * @param  array<string, mixed>  $context
 * @return array<string, mixed>
 */
function menuContextSecurityCategoryPayload(string $name, array $context): array
{
    return [
        'context' => $context,
        'parent_id' => 0,
        'name_hy' => $name,
        'name_ru' => $name,
        'name_en' => $name,
        'sort_order' => 10,
        'active' => '1',
    ];
}

function menuContextSecurityText(string $text): LocalizedText
{
    return LocalizedText::fromArray([
        'hy' => $text,
        'ru' => $text,
        'en' => $text,
    ]);
}
