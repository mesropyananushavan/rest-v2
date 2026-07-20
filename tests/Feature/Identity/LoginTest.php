<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Identity\Infrastructure\Models\UserBranchAssignment;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Modules\Tenancy\Infrastructure\Models\Branch;
use App\Modules\Tenancy\Infrastructure\Models\Tenant;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->assertSee(__('auth.fields.email'), false)
            ->assertSee(__('auth.fields.password'), false)
            ->assertSee(__('auth.login.submit'), false);
    }
});

it('authenticates and logs out an active user with session auth', function (): void {
    $record = loginTenantWithUser('tenant-a', 'manager-a', 'manager-a@smartrest.test');

    $this->withSession(['_token' => loginCsrfToken()])
        ->post(route('login.store'), loginPayload([
            'email' => 'manager-a@smartrest.test',
            'password' => 'password',
        ]))
        ->assertRedirect('/');

    $this->assertAuthenticatedAs($record['user']);

    $this->withSession(['_token' => loginCsrfToken()])
        ->post(route('logout'), ['_token' => loginCsrfToken()])
        ->assertRedirect('/');

    $this->assertGuest();
});

it('rejects invalid credentials without authenticating the user', function (): void {
    loginTenantWithUser('tenant-a', 'manager-a', 'manager-a@smartrest.test');

    $this->withSession(['_token' => loginCsrfToken()])
        ->post(route('login.store'), loginPayload([
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
            'email' => 'manager-a@smartrest.test',
            'password' => 'password',
        ]))
        ->assertRedirect(route('admin.branches.show', ['branch' => (int) $record['branch']->id]));

    $this->get(route('login'))
        ->assertRedirect('/');
});

it('resolves tenant and branch context from the logged-in user through middleware', function (): void {
    $record = loginTenantWithUser('tenant-a', 'manager-a', 'manager-a@smartrest.test');

    Route::middleware(['web', 'auth'])->get('/_test/login-context', fn () => response()->json([
        'tenant_id' => app(TenantResolver::class)->id(),
        'branch_id' => app(BranchContext::class)->id(),
    ]));

    $this->withSession(['_token' => loginCsrfToken()])
        ->post(route('login.store'), loginPayload([
            'email' => 'manager-a@smartrest.test',
            'password' => 'password',
        ]))
        ->assertRedirect('/');

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
            'email' => 'manager-a@smartrest.test',
            'password' => 'password',
        ]))
        ->assertRedirect('/');

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
                'email' => 'missing@smartrest.test',
                'password' => 'wrong-password',
            ]))
            ->assertRedirect();
    }

    $this->withSession(['_token' => loginCsrfToken()])
        ->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
        ->post(route('login.store'), loginPayload([
            'email' => 'missing@smartrest.test',
            'password' => 'wrong-password',
        ]))
        ->assertTooManyRequests();
});

it('logs in with demo users from the deterministic seeders', function (): void {
    $this->seed(DemoSeeder::class);

    foreach (['manager@arat.test', 'manager@northstar.test'] as $email) {
        $this->withSession(['_token' => loginCsrfToken()])
            ->post(route('login.store'), loginPayload([
                'email' => $email,
                'password' => 'password',
            ]))
            ->assertRedirect('/');

        $this->assertAuthenticated();

        $this->withSession(['_token' => loginCsrfToken()])
            ->post(route('logout'), ['_token' => loginCsrfToken()])
            ->assertRedirect('/');
    }
});

/**
 * @return array{tenant: Tenant, branch: Branch, user: User}
 */
function loginTenantWithUser(string $tenantSlug, string $username, string $email): array
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

    $user = User::query()->create([
        'name' => $username,
        'email' => $email,
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
