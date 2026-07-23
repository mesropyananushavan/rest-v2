<?php

declare(strict_types=1);

use App\Modules\Tables\Infrastructure\Models\Hall;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

afterEach(function (): void {
    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();
});

it('seeds deterministic halls visible to demo managers by tenant and branch', function (): void {
    Storage::fake('public');

    $this->seed(DemoSeeder::class);

    $this->withSession(['_token' => tablesDemoCsrfToken()])
        ->post(route('login.store'), tablesDemoLoginPayload('manager@arat.test'))
        ->assertRedirect(route('admin.dashboard'));

    $this->get(route('admin.tables.halls.index'))
        ->assertOk()
        ->assertSee('Գլխավոր սրահ', false)
        ->assertSee('VIP սրահ', false)
        ->assertSee('Տեռաս', false)
        ->assertDontSee('Forest Hall', false)
        ->assertDontSee('Main Room', false);

    $this->withSession(['_token' => tablesDemoCsrfToken()])
        ->post(route('logout'), ['_token' => tablesDemoCsrfToken()])
        ->assertRedirect('/');

    $this->withSession(['_token' => tablesDemoCsrfToken()])
        ->post(route('login.store'), tablesDemoLoginPayload('manager@northstar.test'))
        ->assertRedirect(route('admin.dashboard'));

    $this->get(route('admin.tables.halls.index'))
        ->assertOk()
        ->assertSee('Main Room', false)
        ->assertSee('Patio', false)
        ->assertDontSee('Գլխավոր սրահ', false);

    expect(Hall::query()->count())->toBe(2);
});

function tablesDemoCsrfToken(): string
{
    return 'tables-demo-test-token';
}

/**
 * @return array<string, string>
 */
function tablesDemoLoginPayload(string $email): array
{
    return [
        '_token' => tablesDemoCsrfToken(),
        'email' => $email,
        'password' => 'password',
    ];
}
