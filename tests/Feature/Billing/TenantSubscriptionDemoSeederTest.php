<?php

declare(strict_types=1);

use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Modules\Tenancy\Infrastructure\Models\Tenant;
use App\Modules\Tenancy\Infrastructure\Models\TenantSubscription;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

afterEach(function (): void {
    app(TenantResolver::class)->clear();
});

it('seeds deterministic subscription rows for demo tenants idempotently', function (): void {
    config(['billing.default_grace_days' => 3]);

    $this->seed(DemoSeeder::class);
    $this->seed(DemoSeeder::class);

    $arat = Tenant::query()->where('slug', 'arat-riverside')->firstOrFail();
    $northstar = Tenant::query()->where('slug', 'northstar-bistro')->firstOrFail();

    app(TenantResolver::class)->set((int) $arat->id);

    $aratSubscription = TenantSubscription::query()->firstOrFail();

    app(TenantResolver::class)->set((int) $northstar->id);

    $northstarSubscription = TenantSubscription::query()->firstOrFail();

    expect($aratSubscription->billing_anchor_day)->toBe(1)
        ->and($aratSubscription->next_due_on?->format('Y-m-d'))->toBe('2026-08-01')
        ->and($aratSubscription->grace_days)->toBe(3)
        ->and($aratSubscription->last_paid_on?->format('Y-m-d'))->toBe('2026-07-01')
        ->and($northstarSubscription->billing_anchor_day)->toBe(31)
        ->and($northstarSubscription->next_due_on?->format('Y-m-d'))->toBe('2026-08-31')
        ->and($northstarSubscription->grace_days)->toBe(5)
        ->and($northstarSubscription->last_paid_on?->format('Y-m-d'))->toBe('2026-07-31');

    app(TenantResolver::class)->set((int) $arat->id);
    expect(TenantSubscription::query()->count())->toBe(1);

    app(TenantResolver::class)->set((int) $northstar->id);
    expect(TenantSubscription::query()->count())->toBe(1);
});
