<?php

declare(strict_types=1);

use App\Modules\Tenancy\Contracts\BelongsToTenant;
use App\Modules\Tenancy\Infrastructure\Models\TenantSubscription;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('creates tenant subscriptions with tenant scope and lookup indexes', function (): void {
    expect(Schema::hasTable('tenant_subscriptions'))->toBeTrue()
        ->and(Schema::hasColumns('tenant_subscriptions', [
            'id',
            'tenant_id',
            'billing_anchor_day',
            'next_due_on',
            'grace_days',
            'last_paid_on',
            'created_at',
            'updated_at',
        ]))->toBeTrue()
        ->and(class_uses_recursive(TenantSubscription::class))->toContain(BelongsToTenant::class)
        ->and(class_uses_recursive(TenantSubscription::class))->not->toContain(SoftDeletes::class);

    $indexNames = collect(Schema::getIndexes('tenant_subscriptions'))
        ->pluck('name')
        ->all();

    expect($indexNames)->toContain('tenant_subscriptions_tenant_id_unique')
        ->and($indexNames)->toContain('tenant_subscriptions_suspendable_lookup_idx');
});

it('creates PostgreSQL tenant subscription check constraints', function (): void {
    if (Schema::getConnection()->getDriverName() !== 'pgsql') {
        expect(true)->toBeTrue();

        return;
    }

    $constraints = collect(DB::select(<<<'SQL'
        select conname, contype, pg_get_constraintdef(oid) as definition
        from pg_constraint
        where conrelid = 'tenant_subscriptions'::regclass
        SQL))
        ->mapWithKeys(fn (stdClass $constraint): array => [
            (string) $constraint->conname => [
                'type' => (string) $constraint->contype,
                'definition' => (string) $constraint->definition,
            ],
        ]);

    expect($constraints->get('tenant_subscriptions_billing_anchor_day_chk')['type'] ?? null)->toBe('c')
        ->and($constraints->get('tenant_subscriptions_billing_anchor_day_chk')['definition'] ?? '')->toContain('billing_anchor_day')
        ->and($constraints->get('tenant_subscriptions_billing_anchor_day_chk')['definition'] ?? '')->toContain('31')
        ->and($constraints->get('tenant_subscriptions_grace_days_chk')['type'] ?? null)->toBe('c')
        ->and($constraints->get('tenant_subscriptions_grace_days_chk')['definition'] ?? '')->toContain('grace_days >= 0');
});
