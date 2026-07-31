<?php

declare(strict_types=1);

use App\Modules\Identity\Application\AuthenticateUser;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Identity\Infrastructure\Models\UserBranchAssignment;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantDirectory;
use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Modules\Tenancy\Infrastructure\Models\Branch;
use App\Modules\Tenancy\Infrastructure\Models\Tenant;
use App\Support\Logging\LogContext;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

afterEach(function (): void {
    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();
});

it('renders the translated login form for supported locales', function (): void {
    foreach (['en', 'hy', 'ru'] as $locale) {
        app()->setLocale($locale);

        $this->get(route('login'))
            ->assertOk()
            ->assertSee(__('auth.login.heading'), false)
            ->assertSee(__('auth.fields.tenant_slug'), false)
            ->assertSee(__('auth.fields.email'), false)
            ->assertSee(__('auth.fields.password'), false)
            ->assertSee(__('auth.login.submit'), false)
            ->assertSee('name="tenant_slug"', false);
    }
});

it('authenticates and logs out an active user with session auth', function (): void {
    $record = loginTenantWithUser('tenant-a', 'manager-a', 'manager-a@smartrest.test');
    $oldSessionId = session()->getId();

    $this->withSession(['_token' => loginCsrfToken()])
        ->post(route('login.store'), loginPayload([
            'tenant_slug' => 'tenant-a',
            'email' => 'manager-a@smartrest.test',
            'password' => 'password',
        ]))
        ->assertRedirect(route('admin.dashboard'))
        ->assertSessionHas('tenant_id', (int) $record['tenant']->id);

    $this->assertAuthenticatedAs($record['user']);
    expect(session()->getId())->not->toBe($oldSessionId);

    $this->withSession(['_token' => loginCsrfToken()])
        ->post(route('logout'), ['_token' => loginCsrfToken()])
        ->assertRedirect('/');

    $this->assertGuest();
});

it('rejects invalid credentials without authenticating the user', function (): void {
    loginTenantWithUser('tenant-a', 'manager-a', 'manager-a@smartrest.test');

    $this->withSession(['_token' => loginCsrfToken()])
        ->post(route('login.store'), loginPayload([
            'tenant_slug' => 'tenant-a',
            'email' => 'manager-a@smartrest.test',
            'password' => 'wrong-password',
        ]))
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('redirects guests from protected routes to login and authenticated users away from login', function (): void {
    $record = loginTenantWithUser('tenant-a', 'manager-a', 'manager-a@smartrest.test');

    $this->get(route('admin.branches.show', ['branch' => (int) $record['branch']->id]))
        ->assertRedirect(route('login'));

    $this->withSession(['_token' => loginCsrfToken()])
        ->post(route('login.store'), loginPayload([
            'tenant_slug' => 'tenant-a',
            'email' => 'manager-a@smartrest.test',
            'password' => 'password',
        ]))
        ->assertRedirect(route('admin.branches.show', ['branch' => (int) $record['branch']->id]));

    $this->get(route('login'))
        ->assertRedirect(route('admin.dashboard'));
});

it('resolves tenant and branch context from the logged-in user through middleware', function (): void {
    $record = loginTenantWithUser('tenant-a', 'manager-a', 'manager-a@smartrest.test');

    Route::middleware(['web', 'auth'])->get('/_test/login-context', fn () => response()->json([
        'tenant_id' => app(TenantResolver::class)->id(),
        'branch_id' => app(BranchContext::class)->id(),
    ]));

    $this->withSession(['_token' => loginCsrfToken()])
        ->post(route('login.store'), loginPayload([
            'tenant_slug' => 'tenant-a',
            'email' => 'manager-a@smartrest.test',
            'password' => 'password',
        ]))
        ->assertRedirect(route('admin.dashboard'));

    Auth::forgetGuards();

    $this->get('/_test/login-context')
        ->assertOk()
        ->assertJson([
            'tenant_id' => (int) $record['tenant']->id,
            'branch_id' => (int) $record['branch']->id,
        ]);
});

it('returns 404 for another tenant branch after logging in through the real form flow', function (): void {
    $tenantA = loginTenantWithUser('tenant-a', 'manager-a', 'manager-a@smartrest.test');
    $tenantB = loginTenantWithUser('tenant-b', 'manager-b', 'manager-b@smartrest.test');

    $this->withSession(['_token' => loginCsrfToken()])
        ->post(route('login.store'), loginPayload([
            'tenant_slug' => 'tenant-a',
            'email' => 'manager-a@smartrest.test',
            'password' => 'password',
        ]))
        ->assertRedirect(route('admin.dashboard'));

    $this->get(route('admin.branches.show', ['branch' => (int) $tenantA['branch']->id]))
        ->assertOk()
        ->assertJsonPath('data.id', (int) $tenantA['branch']->id);

    $this->get(route('admin.branches.show', ['branch' => (int) $tenantB['branch']->id]))
        ->assertNotFound();
});

it('rate limits the login endpoint', function (): void {
    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $this->withSession(['_token' => loginCsrfToken()])
            ->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->post(route('login.store'), loginPayload([
                'tenant_slug' => 'tenant-a',
                'email' => 'missing@smartrest.test',
                'password' => 'wrong-password',
            ]))
            ->assertRedirect();
    }

    $this->withSession(['_token' => loginCsrfToken()])
        ->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
        ->post(route('login.store'), loginPayload([
            'tenant_slug' => 'tenant-a',
            'email' => 'missing@smartrest.test',
            'password' => 'wrong-password',
        ]))
        ->assertTooManyRequests();
});

it('requires a tenant slug without retaining selected tenant state or password input', function (): void {
    $record = loginTenantWithUser('tenant-a', 'manager-a', 'manager-a@smartrest.test');

    $this->withSession([
        '_token' => loginCsrfToken(),
        'tenant_id' => (int) $record['tenant']->id,
        'branch_id' => (int) $record['branch']->id,
    ])
        ->post(route('login.store'), loginPayload([
            'email' => 'manager-a@smartrest.test',
            'password' => 'password',
        ]))
        ->assertSessionHasErrors('tenant_slug')
        ->assertSessionHasInput('email', 'manager-a@smartrest.test')
        ->assertSessionMissing('tenant_id')
        ->assertSessionMissing('branch_id');

    expect(session()->getOldInput())->not->toHaveKey('password')
        ->and(app(TenantResolver::class)->id())->toBeNull()
        ->and(app(BranchContext::class)->id())->toBeNull()
        ->and(LogContext::current()['tenant_id'])->toBeNull()
        ->and(LogContext::current()['branch_id'])->toBeNull();

    $this->assertGuest();
});

it('renders tenant slug validation errors with localized field labels', function (): void {
    foreach (['en', 'hy', 'ru'] as $locale) {
        app()->setLocale($locale);

        $this->withSession(['_token' => loginCsrfToken()])
            ->post(route('login.store'), loginPayload([
                'email' => 'manager-a@smartrest.test',
                'password' => 'password',
            ]))
            ->assertSessionHasErrors('tenant_slug');

        $error = session('errors')?->get('tenant_slug')[0] ?? '';

        expect($error)->toContain(__('auth.fields.tenant_slug'));
    }
});

it('rejects unknown tenant slugs with the generic authentication error', function (): void {
    loginTenantWithUser('tenant-a', 'manager-a', 'manager-a@smartrest.test');

    $this->withSession(['_token' => loginCsrfToken()])
        ->post(route('login.store'), loginPayload([
            'tenant_slug' => 'missing-tenant',
            'email' => 'manager-a@smartrest.test',
            'password' => 'password',
        ]))
        ->assertSessionHasErrors(['email' => __('auth.failed')])
        ->assertSessionHasInput('tenant_slug', 'missing-tenant')
        ->assertSessionHasInput('email', 'manager-a@smartrest.test')
        ->assertSessionMissing('tenant_id')
        ->assertSessionMissing('branch_id');

    $this->assertGuest();
    expect(session()->getOldInput())->not->toHaveKey('password');
});

it('rejects suspended tenants before credential verification', function (): void {
    loginTenantWithUser('tenant-a', 'manager-a', 'manager-a@smartrest.test', tenantStatus: 'suspended');

    $this->withSession(['_token' => loginCsrfToken()])
        ->post(route('login.store'), loginPayload([
            'tenant_slug' => 'tenant-a',
            'email' => 'manager-a@smartrest.test',
            'password' => 'password',
        ]))
        ->assertSessionHasErrors(['email' => __('auth.failed')])
        ->assertSessionMissing('tenant_id')
        ->assertSessionMissing('branch_id');

    $this->assertGuest();
});

it('rejects unknown and inactive users inside the selected tenant', function (): void {
    loginTenantWithUser('tenant-a', 'inactive-a', 'inactive-a@smartrest.test', userActive: false);

    foreach (['missing@smartrest.test', 'inactive-a@smartrest.test'] as $email) {
        $this->withSession(['_token' => loginCsrfToken()])
            ->post(route('login.store'), loginPayload([
                'tenant_slug' => 'tenant-a',
                'email' => $email,
                'password' => 'password',
            ]))
            ->assertSessionHasErrors(['email' => __('auth.failed')])
            ->assertSessionMissing('tenant_id')
            ->assertSessionMissing('branch_id');

        $this->assertGuest();
    }
});

it('authenticates duplicate emails only inside the submitted tenant', function (): void {
    $tenantA = loginTenantWithUser('tenant-a', 'manager-a', 'shared@smartrest.test', password: 'tenant-a-password');
    $tenantB = loginTenantWithUser('tenant-b', 'manager-b', 'shared@smartrest.test', password: 'tenant-b-password');

    $this->withSession(['_token' => loginCsrfToken()])
        ->post(route('login.store'), loginPayload([
            'tenant_slug' => 'tenant-b',
            'email' => 'shared@smartrest.test',
            'password' => 'tenant-b-password',
        ]))
        ->assertRedirect(route('admin.dashboard'))
        ->assertSessionHas('tenant_id', (int) $tenantB['tenant']->id);

    $this->assertAuthenticatedAs($tenantB['user']);

    Auth::logout();
    app(TenantResolver::class)->clear();

    $this->withSession(['_token' => loginCsrfToken()])
        ->post(route('login.store'), loginPayload([
            'tenant_slug' => 'tenant-a',
            'email' => 'shared@smartrest.test',
            'password' => 'tenant-b-password',
        ]))
        ->assertSessionHasErrors(['email' => __('auth.failed')])
        ->assertSessionMissing('tenant_id')
        ->assertSessionMissing('branch_id');

    expect((int) $tenantA['user']->id)->not->toBe((int) $tenantB['user']->id);
    $this->assertGuest();
});

it('cleans tenant branch and log context after expected authentication failures', function (): void {
    $record = loginTenantWithUser('tenant-a', 'manager-a', 'manager-a@smartrest.test');

    app(TenantResolver::class)->set((int) $record['tenant']->id);
    app(BranchContext::class)->set((int) $record['branch']->id);
    LogContext::refreshRuntimeContext('identity');

    $this->withSession([
        '_token' => loginCsrfToken(),
        'tenant_id' => (int) $record['tenant']->id,
        'branch_id' => (int) $record['branch']->id,
    ])
        ->post(route('login.store'), loginPayload([
            'tenant_slug' => 'tenant-a',
            'email' => 'manager-a@smartrest.test',
            'password' => 'wrong-password',
        ]))
        ->assertSessionHasErrors(['email' => __('auth.failed')])
        ->assertSessionMissing('tenant_id')
        ->assertSessionMissing('branch_id');

    expect(app(TenantResolver::class)->id())->toBeNull()
        ->and(app(BranchContext::class)->id())->toBeNull()
        ->and(LogContext::current()['tenant_id'])->toBeNull()
        ->and(LogContext::current()['branch_id'])->toBeNull()
        ->and(LogContext::current()['user_id'])->toBeNull();
});

it('cleans context when tenant lookup throws unexpectedly', function (): void {
    app(TenantResolver::class)->set(123);
    app(BranchContext::class)->set(456);
    LogContext::refreshRuntimeContext('identity');

    app()->instance(TenantDirectory::class, new class implements TenantDirectory
    {
        public function activeTenantIds(): array
        {
            throw new RuntimeException('activeTenantIds must not be used for login.');
        }

        public function isServiceable(int $tenantId): bool
        {
            return true;
        }

        public function serviceableTenantIdForSlug(string $slug): ?int
        {
            throw new RuntimeException('tenant lookup failed');
        }

        public function tenantName(int $tenantId): ?string
        {
            return null;
        }

        public function branchSummariesForIds(array $branchIds): array
        {
            return [];
        }
    });

    expect(fn () => app(AuthenticateUser::class)('tenant-a', 'manager-a@smartrest.test', 'password'))
        ->toThrow(RuntimeException::class, 'tenant lookup failed');

    expect(app(TenantResolver::class)->id())->toBeNull()
        ->and(app(BranchContext::class)->id())->toBeNull()
        ->and(LogContext::current()['tenant_id'])->toBeNull()
        ->and(LogContext::current()['branch_id'])->toBeNull()
        ->and(LogContext::current()['user_id'])->toBeNull();
});

it('cleans context when password verification throws unexpectedly', function (): void {
    $record = loginTenantWithUser('tenant-a', 'manager-a', 'manager-a@smartrest.test');

    app()->instance(TenantDirectory::class, new class((int) $record['tenant']->id) implements TenantDirectory
    {
        public function __construct(private readonly int $tenantId) {}

        public function activeTenantIds(): array
        {
            throw new RuntimeException('activeTenantIds must not be used for login.');
        }

        public function isServiceable(int $tenantId): bool
        {
            return $tenantId === $this->tenantId;
        }

        public function serviceableTenantIdForSlug(string $slug): ?int
        {
            return $slug === 'tenant-a' ? $this->tenantId : null;
        }

        public function tenantName(int $tenantId): ?string
        {
            return null;
        }

        public function branchSummariesForIds(array $branchIds): array
        {
            return [];
        }
    });

    Hash::shouldReceive('check')
        ->once()
        ->andThrow(new RuntimeException('hash unavailable'));

    expect(fn () => app(AuthenticateUser::class)('tenant-a', 'manager-a@smartrest.test', 'password'))
        ->toThrow(RuntimeException::class, 'hash unavailable');

    expect(app(TenantResolver::class)->id())->toBeNull()
        ->and(app(BranchContext::class)->id())->toBeNull()
        ->and(LogContext::current()['tenant_id'])->toBeNull()
        ->and(LogContext::current()['branch_id'])->toBeNull()
        ->and(LogContext::current()['user_id'])->toBeNull();
});

it('does not call active tenant fleet reads while authenticating', function (): void {
    $record = loginTenantWithUser('tenant-a', 'manager-a', 'manager-a@smartrest.test');

    app()->instance(TenantDirectory::class, new class((int) $record['tenant']->id) implements TenantDirectory
    {
        public function __construct(private readonly int $tenantId) {}

        public function activeTenantIds(): array
        {
            throw new RuntimeException('activeTenantIds must not be used for login.');
        }

        public function isServiceable(int $tenantId): bool
        {
            return $tenantId === $this->tenantId;
        }

        public function serviceableTenantIdForSlug(string $slug): ?int
        {
            return $slug === 'tenant-a' ? $this->tenantId : null;
        }

        public function tenantName(int $tenantId): ?string
        {
            return null;
        }

        public function branchSummariesForIds(array $branchIds): array
        {
            return [];
        }
    });

    $user = app(AuthenticateUser::class)('tenant-a', 'manager-a@smartrest.test', 'password');

    expect($user)->toBeInstanceOf(User::class)
        ->and((int) $user->id)->toBe((int) $record['user']->id);
});

it('keeps authentication query count bounded with many unrelated tenants', function (): void {
    for ($tenant = 1; $tenant <= 30; $tenant++) {
        loginTenantWithUser("noise-tenant-{$tenant}", "noise-user-{$tenant}", "noise-{$tenant}@smartrest.test");
    }

    $record = loginTenantWithUser('tenant-a', 'manager-a', 'manager-a@smartrest.test');

    DB::flushQueryLog();
    DB::enableQueryLog();

    $this->withSession(['_token' => loginCsrfToken()])
        ->post(route('login.store'), loginPayload([
            'tenant_slug' => 'tenant-a',
            'email' => 'manager-a@smartrest.test',
            'password' => 'password',
        ]))
        ->assertRedirect(route('admin.dashboard'))
        ->assertSessionHas('tenant_id', (int) $record['tenant']->id);

    $queries = collect(DB::getQueryLog())->pluck('query');

    expect($queries->filter(fn (string $query): bool => str_contains($query, 'from "tenants"'))->count())->toBe(1)
        ->and($queries->filter(fn (string $query): bool => str_contains($query, 'from "users"'))->count())->toBe(1)
        ->and($queries->count())->toBeLessThanOrEqual(6);
});

it('logs in with demo users from the deterministic seeders', function (): void {
    $this->seed(DemoSeeder::class);

    foreach ([
        'arat-riverside' => 'manager@arat.test',
        'northstar-bistro' => 'manager@northstar.test',
    ] as $tenantSlug => $email) {
        $this->withSession(['_token' => loginCsrfToken()])
            ->post(route('login.store'), loginPayload([
                'tenant_slug' => $tenantSlug,
                'email' => $email,
                'password' => 'password',
            ]))
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticated();

        $this->withSession(['_token' => loginCsrfToken()])
            ->post(route('logout'), ['_token' => loginCsrfToken()])
            ->assertRedirect('/');
    }
});

/**
 * @return array{tenant: Tenant, branch: Branch, user: User}
 */
function loginTenantWithUser(
    string $tenantSlug,
    string $username,
    string $email,
    string $tenantStatus = 'active',
    bool $userActive = true,
    string $password = 'password',
): array {
    $tenant = Tenant::query()->create([
        'name' => str($tenantSlug)->headline()->toString(),
        'slug' => $tenantSlug,
        'default_locale' => 'hy',
        'currency' => 'AMD',
        'status' => $tenantStatus,
    ]);

    app(TenantResolver::class)->set((int) $tenant->id);

    $branch = Branch::query()->create([
        'name' => "{$tenantSlug} Branch",
        'timezone' => 'Asia/Yerevan',
        'status' => 'active',
    ]);

    app(BranchContext::class)->set((int) $branch->id);

    $user = User::query()->create([
        'name' => $username,
        'email' => $email,
        'username' => $username,
        'default_locale' => 'hy',
        'active' => $userActive,
        'password' => Hash::make($password),
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

function loginCsrfToken(): string
{
    return 'login-test-token';
}

/**
 * @param  array<string, string>  $payload
 * @return array<string, string>
 */
function loginPayload(array $payload): array
{
    return ['_token' => loginCsrfToken()] + $payload;
}
