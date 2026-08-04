<?php

declare(strict_types=1);

use App\Modules\Orders\Contracts\PayableOrderReader;
use App\Modules\Orders\Domain\OrdersDomainException;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Modules\Tenancy\Infrastructure\Models\Branch;
use App\Modules\Tenancy\Infrastructure\Models\Tenant;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('returns a payable snapshot for an open positive-total order with exact money values', function (): void {
    $record = payableOrderFixture();
    payableOrderActingIn($record);
    $order = payableOrderCreateOrder($record['branch'], [
        'subtotal_minor' => 123456789012,
        'discount_minor' => 456,
        'total_minor' => 123456788556,
        'currency' => 'AMD',
    ]);

    $snapshot = app(PayableOrderReader::class)->findPayable((int) $order->id);

    expect($snapshot->orderId)->toBe((int) $order->id)
        ->and($snapshot->tenantId)->toBe((int) $record['tenant']->id)
        ->and($snapshot->branchId)->toBe((int) $record['branch']->id)
        ->and($snapshot->status)->toBe('open')
        ->and($snapshot->currency)->toBe('AMD')
        ->and($snapshot->totalMinor)->toBe(123456788556)
        ->and($snapshot->currentRemainingPayableMinor())->toBe(123456788556);
});

it('rejects zero-total open orders with a stable domain code', function (): void {
    $record = payableOrderFixture();
    payableOrderActingIn($record);
    $order = payableOrderCreateOrder($record['branch'], ['total_minor' => 0]);

    try {
        app(PayableOrderReader::class)->findPayable((int) $order->id);
        $this->fail('Expected zero-total order to be rejected.');
    } catch (OrdersDomainException $exception) {
        expect($exception->errorCode())->toBe('orders.order_not_payable');
    }
});

it('rejects non-open orders with a stable domain code', function (string $status): void {
    $record = payableOrderFixture();
    payableOrderActingIn($record);
    $order = payableOrderCreateOrder($record['branch'], [
        'status' => $status,
        'closed_at' => now(),
        'total_minor' => 2500,
    ]);

    try {
        app(PayableOrderReader::class)->findPayable((int) $order->id);
        $this->fail('Expected non-open order to be rejected.');
    } catch (OrdersDomainException $exception) {
        expect($exception->errorCode())->toBe('orders.order_not_open');
    }
})->with(['cancelled', 'closed']);

it('hides foreign tenant and foreign branch orders with not found semantics', function (): void {
    $tenantA = payableOrderFixture('payable-a');
    $tenantB = payableOrderFixture('payable-b');

    payableOrderActingIn($tenantB);
    $foreignTenantOrder = payableOrderCreateOrder($tenantB['branch'], ['total_minor' => 3000]);

    payableOrderActingIn($tenantA);
    $otherBranch = Branch::query()->create([
        'name' => 'Other Payable Branch',
        'timezone' => 'Asia/Yerevan',
        'status' => 'active',
    ]);
    $foreignBranchOrder = payableOrderCreateOrder($otherBranch, ['total_minor' => 4000]);

    expect(fn () => app(PayableOrderReader::class)->findPayable((int) $foreignTenantOrder->id))
        ->toThrow(ModelNotFoundException::class)
        ->and(fn () => app(PayableOrderReader::class)->findPayable((int) $foreignBranchOrder->id))
        ->toThrow(ModelNotFoundException::class);
});

it('does not mutate orders or write audit rows during payable reads', function (): void {
    $record = payableOrderFixture();
    payableOrderActingIn($record);
    $order = payableOrderCreateOrder($record['branch'], ['total_minor' => 5000]);
    $before = (array) DB::table('orders')->where('id', (int) $order->id)->first();

    app(PayableOrderReader::class)->findPayable((int) $order->id);

    $after = (array) DB::table('orders')->where('id', (int) $order->id)->first();

    expect($after)->toBe($before)
        ->and(DB::table('audit_logs')->count())->toBe(0);
});

it('returns a locked payable snapshot inside a caller owned transaction', function (): void {
    $record = payableOrderFixture();
    payableOrderActingIn($record);
    $order = payableOrderCreateOrder($record['branch'], ['total_minor' => 6000]);

    DB::transaction(function () use ($order): void {
        $snapshot = app(PayableOrderReader::class)->lockPayableForUpdate((int) $order->id);

        expect($snapshot->orderId)->toBe((int) $order->id)
            ->and($snapshot->totalMinor)->toBe(6000);
    });
});

it('keeps order translation key sets identical across supported locales after payable errors', function (): void {
    $keys = collect(['hy', 'ru', 'en'])
        ->mapWithKeys(fn (string $locale): array => [$locale => payableOrderFlattenKeys(require lang_path("{$locale}/orders.php"))]);

    expect($keys['hy'])->toBe($keys['en'])
        ->and($keys['ru'])->toBe($keys['en']);
});

/**
 * @return array{tenant: Tenant, branch: Branch}
 */
function payableOrderFixture(string $slugPrefix = 'payable'): array
{
    $tenant = Tenant::query()->create([
        'name' => 'Payable Tenant '.str()->random(8),
        'slug' => $slugPrefix.'-'.str()->random(8),
        'default_locale' => 'en',
        'currency' => 'AMD',
        'status' => 'active',
    ]);

    app(TenantResolver::class)->set((int) $tenant->id);

    $branch = Branch::query()->create([
        'name' => 'Payable Branch',
        'timezone' => 'Asia/Yerevan',
        'status' => 'active',
    ]);

    return [
        'tenant' => $tenant,
        'branch' => $branch,
    ];
}

/**
 * @param  array{tenant: Tenant, branch: Branch}  $record
 */
function payableOrderActingIn(array $record): void
{
    app(TenantResolver::class)->set((int) $record['tenant']->id);
    app(BranchContext::class)->set((int) $record['branch']->id);
}

/**
 * @param  array<string, mixed>  $overrides
 */
function payableOrderCreateOrder(Branch $branch, array $overrides = []): Order
{
    return Order::query()->create(array_merge([
        'branch_id' => (int) $branch->id,
        'type' => 'fast_food',
        'status' => 'open',
        'table_id' => null,
        'opened_at' => now(),
        'closed_at' => null,
        'client_count' => 1,
        'subtotal_minor' => 1000,
        'discount_minor' => 0,
        'total_minor' => 1000,
        'currency' => 'AMD',
    ], $overrides));
}

/**
 * @return list<string>
 */
function payableOrderFlattenKeys(array $values, string $prefix = ''): array
{
    $keys = [];

    foreach ($values as $key => $value) {
        $fullKey = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

        if (is_array($value)) {
            array_push($keys, ...payableOrderFlattenKeys($value, $fullKey));
        } else {
            $keys[] = $fullKey;
        }
    }

    sort($keys);

    return $keys;
}
