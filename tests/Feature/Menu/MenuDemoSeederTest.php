<?php

declare(strict_types=1);

use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

afterEach(function (): void {
    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();
});

it('seeds deterministic menu data visible to demo managers by tenant', function (): void {
    $this->seed(DemoSeeder::class);

    $this->withSession(['_token' => menuDemoCsrfToken()])
        ->post(route('login.store'), menuDemoLoginPayload('manager@arat.test'))
        ->assertRedirect(route('admin.dashboard'));

    $this->get(route('admin.menu.index'))
        ->assertOk()
        ->assertSee('Լոռի ձվածեղ', false)
        ->assertSee('Երեւանյան աղցան', false)
        ->assertDontSee('Northstar burger', false);

    $this->withSession(['_token' => menuDemoCsrfToken()])
        ->post(route('logout'), ['_token' => menuDemoCsrfToken()])
        ->assertRedirect('/');

    $this->withSession(['_token' => menuDemoCsrfToken()])
        ->post(route('login.store'), menuDemoLoginPayload('manager@northstar.test'))
        ->assertRedirect(route('admin.dashboard'));

    $this->get(route('admin.menu.index'))
        ->assertOk()
        ->assertSee('Northstar burger', false)
        ->assertSee('Corn chowder', false)
        ->assertDontSee('Լոռի ձվածեղ', false);
});

function menuDemoCsrfToken(): string
{
    return 'menu-demo-test-token';
}

/**
 * @return array<string, string>
 */
function menuDemoLoginPayload(string $email): array
{
    return [
        '_token' => menuDemoCsrfToken(),
        'email' => $email,
        'password' => 'password',
    ];
}
