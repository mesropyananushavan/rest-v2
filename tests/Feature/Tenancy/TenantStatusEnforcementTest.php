<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Identity\Infrastructure\Models\UserBranchAssignment;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Modules\Tenancy\Http\Middleware\EnsureTenantIsServiceable;
use App\Modules\Tenancy\Http\Middleware\ResolveBranch;
use App\Modules\Tenancy\Http\Middleware\ResolveTenant;
use App\Modules\Tenancy\Infrastructure\Models\Branch;
use App\Modules\Tenancy\Infrastructure\Models\Tenant;
use Database\Seeders\DemoSeeder;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;

uses(RefreshDatabase::class);

afterEach(function (): void {
    app()->detectEnvironment(fn (): string => 'testing');
    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();
});

it('allows an active tenant to log in and reach the admin dashboard', function (): void {
    tenantStatusUser('tenant-status-active', 'active-manager', 'active-manager@smartrest.test');

    $this->withSession(['_token' => tenantStatusCsrfToken()])
        ->withServerVariables(['REMOTE_ADDR' => '203.0.113.101'])
        ->post(route('login.store'), tenantStatusLoginPayload([
            'email' => 'active-manager@smartrest.test',
            'password' => 'password',
        ]))
        ->assertRedirect(route('admin.dashboard'));

    $this->get(route('admin.dashboard'))
        ->assertOk();
});

it('blocks an established html session on the next request when the tenant is suspended', function (): void {
    $record = tenantStatusUser('tenant-status-html', 'html-manager', 'html-manager@smartrest.test');

    $this->withSession(['_token' => tenantStatusCsrfToken()])
        ->withServerVariables(['REMOTE_ADDR' => '203.0.113.102'])
        ->post(route('login.store'), tenantStatusLoginPayload([
            'email' => 'html-manager@smartrest.test',
            'password' => 'password',
        ]))
        ->assertRedirect(route('admin.dashboard'));

    tenantStatusSuspend((int) $record['tenant']->id);

    $this->get(route('admin.dashboard'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => __('auth.tenant_suspended')])
        ->assertSessionMissing('tenant_id')
        ->assertSessionMissing('branch_id');

    $this->assertGuest();
});

it('returns the existing json error envelope when an api tenant is suspended', function (): void {
    $this->seed(DemoSeeder::class);

    $tenantId = (int) Tenant::query()
        ->where('slug', 'arat-riverside')
        ->valueOrFail('id');

    $this->withSession(['_token' => tenantStatusCsrfToken()])
        ->withServerVariables(['REMOTE_ADDR' => '203.0.113.103'])
        ->post(route('login.store'), tenantStatusLoginPayload([
            'email' => 'manager@arat.test',
            'password' => 'password',
        ]))
        ->assertRedirect(route('admin.dashboard'));

    tenantStatusSuspend($tenantId);

    $this->getJson(route('api.v1.menu-items.index'))
        ->assertForbidden()
        ->assertJsonPath('errors.0.code', 'tenant.suspended')
        ->assertJsonPath('errors.0.message', __('api.errors.tenant_suspended'))
        ->assertJsonPath('errors.0.field', null)
        ->assertJsonStructure([
            'errors' => [
                ['code', 'message', 'field'],
            ],
            'meta' => ['request_id'],
        ])
        ->assertJsonMissingPath('data');
});

it('allows an active tenant to complete a real http livewire update', function (): void {
    tenantStatusUser('tenant-status-livewire-active', 'livewire-active-manager', 'livewire-active-manager@smartrest.test');

    $this->withSession(['_token' => tenantStatusCsrfToken()])
        ->withServerVariables(['REMOTE_ADDR' => '203.0.113.104'])
        ->post(route('login.store'), tenantStatusLoginPayload([
            'email' => 'livewire-active-manager@smartrest.test',
            'password' => 'password',
        ]))
        ->assertRedirect(route('admin.dashboard'));

    $snapshot = tenantStatusDashboardLivewireSnapshot($this->get(route('admin.dashboard'))->assertOk());

    $this->withSession(['_token' => tenantStatusCsrfToken()])
        ->withHeader('X-Livewire', '1')
        ->withHeader('X-CSRF-TOKEN', tenantStatusCsrfToken())
        ->withServerVariables(['REMOTE_ADDR' => '203.0.113.105'])
        ->postJson(route('default-livewire.update'), tenantStatusLivewirePayload($snapshot))
        ->assertOk()
        ->assertJsonStructure([
            'components' => [
                ['snapshot', 'effects'],
            ],
        ]);
});

it('blocks a real http livewire update when the tenant is suspended', function (): void {
    $record = tenantStatusUser('tenant-status-livewire-suspended', 'livewire-suspended-manager', 'livewire-suspended-manager@smartrest.test');

    $this->withSession(['_token' => tenantStatusCsrfToken()])
        ->withServerVariables(['REMOTE_ADDR' => '203.0.113.106'])
        ->post(route('login.store'), tenantStatusLoginPayload([
            'email' => 'livewire-suspended-manager@smartrest.test',
            'password' => 'password',
        ]))
        ->assertRedirect(route('admin.dashboard'));

    $snapshot = tenantStatusDashboardLivewireSnapshot($this->get(route('admin.dashboard'))->assertOk());

    tenantStatusSuspend((int) $record['tenant']->id);

    $this->withSession(['_token' => tenantStatusCsrfToken()])
        ->withHeader('X-Livewire', '1')
        ->withHeader('X-CSRF-TOKEN', tenantStatusCsrfToken())
        ->withServerVariables(['REMOTE_ADDR' => '203.0.113.107'])
        ->postJson(route('default-livewire.update'), tenantStatusLivewirePayload($snapshot))
        ->assertForbidden()
        ->assertJsonPath('errors.0.code', 'tenant.suspended')
        ->assertJsonPath('errors.0.message', __('api.errors.tenant_suspended'))
        ->assertJsonPath('errors.0.field', null)
        ->assertJsonStructure([
            'errors' => [
                ['code', 'message', 'field'],
            ],
            'meta' => ['request_id'],
        ])
        ->assertJsonMissingPath('data');

    $this->assertAuthenticated();
});

it('rejects login when the session already carries a suspended tenant id', function (): void {
    $record = tenantStatusUser('tenant-status-session', 'session-manager', 'session-manager@smartrest.test');

    tenantStatusSuspend((int) $record['tenant']->id);

    $this->withSession([
        '_token' => tenantStatusCsrfToken(),
        'tenant_id' => (int) $record['tenant']->id,
    ])
        ->withServerVariables(['REMOTE_ADDR' => '203.0.113.108'])
        ->post(route('login.store'), tenantStatusLoginPayload([
            'email' => 'session-manager@smartrest.test',
            'password' => 'password',
        ]))
        ->assertSessionHasErrors(['email' => __('auth.failed')]);

    $this->assertGuest();
});

it('rejects login when a non-production tenant header names a suspended tenant', function (): void {
    $record = tenantStatusUser('tenant-status-header', 'header-manager', 'header-manager@smartrest.test');

    tenantStatusSuspend((int) $record['tenant']->id);

    $this->withSession(['_token' => tenantStatusCsrfToken()])
        ->withHeader('X-Tenant-ID', (string) $record['tenant']->id)
        ->withServerVariables(['REMOTE_ADDR' => '203.0.113.109'])
        ->post(route('login.store'), tenantStatusLoginPayload([
            'email' => 'header-manager@smartrest.test',
            'password' => 'password',
        ]))
        ->assertSessionHasErrors(['email' => __('auth.failed')]);

    $this->assertGuest();
});

it('keeps logout reachable when the current tenant is suspended', function (): void {
    $record = tenantStatusUser('tenant-status-logout', 'logout-manager', 'logout-manager@smartrest.test');

    $this->withSession(['_token' => tenantStatusCsrfToken()])
        ->withServerVariables(['REMOTE_ADDR' => '203.0.113.110'])
        ->post(route('login.store'), tenantStatusLoginPayload([
            'email' => 'logout-manager@smartrest.test',
            'password' => 'password',
        ]))
        ->assertRedirect(route('admin.dashboard'));

    tenantStatusSuspend((int) $record['tenant']->id);

    $this->withSession(['_token' => tenantStatusCsrfToken()])
        ->post(route('logout'), ['_token' => tenantStatusCsrfToken()])
        ->assertRedirect('/');

    $this->assertGuest();
});

it('keeps login and health reachable for guests with a suspended tenant session', function (): void {
    $record = tenantStatusUser('tenant-status-guest', 'guest-manager', 'guest-manager@smartrest.test');

    tenantStatusSuspend((int) $record['tenant']->id);

    $this->withSession(['tenant_id' => (int) $record['tenant']->id])
        ->get(route('login'))
        ->assertOk();

    $this->withSession(['tenant_id' => (int) $record['tenant']->id])
        ->get('/up')
        ->assertOk();

    $this->assertGuest();
});

it('requires tenant serviceability middleware on authenticated routes except logout', function (): void {
    $allowlistedRouteNames = ['logout'];
    $missing = [];
    $router = app(Router::class);

    foreach (Route::getRoutes() as $route) {
        $middleware = $router->gatherRouteMiddleware($route);
        $middlewareClasses = array_map(
            fn (string $middleware): string => explode(':', $middleware, 2)[0],
            $middleware,
        );
        $hasAuth = collect($middlewareClasses)
            ->contains(fn (string $middleware): bool => is_a($middleware, AuthenticatesRequests::class, true));

        if (! $hasAuth || in_array($route->getName(), $allowlistedRouteNames, true)) {
            continue;
        }

        if (! in_array(EnsureTenantIsServiceable::class, $middlewareClasses, true)) {
            $missing[] = $route->getName() ?? $route->uri();
        }
    }

    expect($missing)->toBe([]);
});

it('registers only tenant serviceability as app-specific livewire persistent middleware', function (): void {
    $persistentMiddleware = Livewire::getPersistentMiddleware();

    expect($persistentMiddleware)
        ->toContain(EnsureTenantIsServiceable::class)
        ->not->toContain(ResolveTenant::class)
        ->not->toContain(ResolveBranch::class);
});

/**
 * @return array{tenant: Tenant, branch: Branch, user: User}
 */
function tenantStatusUser(string $tenantSlug, string $username, string $email): array
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

function tenantStatusSuspend(int $tenantId): void
{
    DB::table('tenants')
        ->where('id', $tenantId)
        ->update(['status' => 'suspended']);

    Auth::forgetGuards();
}

function tenantStatusDashboardLivewireSnapshot(TestResponse $response): string
{
    preg_match('/wire:snapshot="([^"]+)"/', $response->getContent(), $matches);

    expect($matches[1] ?? null)->not->toBeNull();

    $snapshot = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5);
    $decodedSnapshot = json_decode($snapshot, true, flags: JSON_THROW_ON_ERROR);

    expect($decodedSnapshot['memo']['path'] ?? null)->toBe('admin')
        ->and($decodedSnapshot['memo']['method'] ?? null)->toBe('GET');

    return $snapshot;
}

/**
 * @return array<string, list<array{snapshot: string, updates: array<string, mixed>, calls: list<array<string, mixed>>}>>
 */
function tenantStatusLivewirePayload(string $snapshot): array
{
    return [
        'components' => [
            [
                'snapshot' => $snapshot,
                'updates' => ['categoryCount' => 0],
                'calls' => [],
            ],
        ],
    ];
}

function tenantStatusCsrfToken(): string
{
    return 'tenant-status-token';
}

/**
 * @param  array<string, string>  $payload
 * @return array<string, string>
 */
function tenantStatusLoginPayload(array $payload): array
{
    return ['_token' => tenantStatusCsrfToken()] + $payload;
}
