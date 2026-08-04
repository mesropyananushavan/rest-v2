<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Models\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Identity\Infrastructure\Models\UserBranchAssignment;
use App\Modules\Menu\Infrastructure\Models\MenuCategory;
use App\Modules\Menu\Infrastructure\Models\MenuItem;
use App\Modules\Orders\Application\AddItem;
use App\Modules\Orders\Application\OpenOrder;
use App\Modules\Orders\Contracts\PayableOrderReader;
use App\Modules\Orders\Domain\OrdersDomainException;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Orders\Infrastructure\Models\OrderItem;
use App\Modules\Orders\Infrastructure\Models\OrderSubtable;
use App\Modules\Tables\Infrastructure\Models\Hall;
use App\Modules\Tables\Infrastructure\Models\Table;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Modules\Tenancy\Infrastructure\Models\Branch;
use App\Modules\Tenancy\Infrastructure\Models\Tenant;
use App\Support\Logging\LogContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\Process\Process;

beforeEach(function (): void {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Orders concurrency correctness is PostgreSQL-only; SQLite is only a regression guard.');
    }

    Artisan::call('migrate:fresh', ['--force' => true]);
    ordersConcurrencySetTimeouts();
});

afterEach(function (): void {
    app(BranchContext::class)->clear();
    app(TenantResolver::class)->clear();
    LogContext::clear();
});

it('locks parent orders before order items for concurrent add change remove and move operations', function (): void {
    foreach (['add_item', 'change_qty', 'remove_item', 'move_item'] as $mode) {
        $record = ordersConcurrencyFixture();
        ordersConcurrencyActingIn($record);
        $sourceOrder = app(OpenOrder::class)((int) $record['tables'][0]->id);
        $targetOrder = app(OpenOrder::class)((int) $record['tables'][1]->id);
        $line = app(AddItem::class)((int) $sourceOrder->id, (int) $record['menu_item']->id, 1);
        $prefix = ordersConcurrencyPrefix($mode);
        $startFile = "{$prefix}.start";
        $payload = ordersConcurrencyPayload($record, $startFile, ['mode' => $mode]);

        $payload['order_id'] = (int) $sourceOrder->id;
        $payload['order_item_id'] = (int) $line->id;
        $payload['menu_item_id'] = (int) $record['menu_item']->id;
        $payload['target_order_id'] = (int) $targetOrder->id;

        DB::beginTransaction();

        try {
            DB::statement('select id from orders where id = ? for update', [(int) $sourceOrder->id]);

            $process = ordersConcurrencyStartWorker($payload);
            touch($startFile);
            usleep(400_000);

            DB::statement('select id from order_items where id = ? for update', [(int) $line->id]);
            DB::commit();
        } catch (QueryException $exception) {
            DB::rollBack();

            throw $exception;
        }

        $result = ordersConcurrencyWaitFor($process);

        ordersConcurrencyAssertOk($result);

        expect(Order::query()->findOrFail((int) $sourceOrder->id)->total_minor)
            ->toBe((int) OrderItem::query()->where('order_id', (int) $sourceOrder->id)->sum('total_minor'));
    }
});

it('moves items between two orders in opposite directions without deadlocks', function (): void {
    $record = ordersConcurrencyFixture();
    ordersConcurrencyActingIn($record);
    $orderA = app(OpenOrder::class)((int) $record['tables'][0]->id);
    $orderB = app(OpenOrder::class)((int) $record['tables'][1]->id);
    $itemA = app(AddItem::class)((int) $orderA->id, (int) $record['menu_item']->id, 1);
    $itemB = app(AddItem::class)((int) $orderB->id, (int) $record['other_menu_item']->id, 1);
    $prefix = ordersConcurrencyPrefix('cross-order-move');
    $startFile = "{$prefix}.start";

    $workerA = ordersConcurrencyStartWorker(ordersConcurrencyPayload($record, $startFile, [
        'mode' => 'move_item',
        'order_item_id' => (int) $itemA->id,
        'target_order_id' => (int) $orderB->id,
    ]));
    $workerB = ordersConcurrencyStartWorker(ordersConcurrencyPayload($record, $startFile, [
        'mode' => 'move_item',
        'order_item_id' => (int) $itemB->id,
        'target_order_id' => (int) $orderA->id,
    ]));

    touch($startFile);

    $resultA = ordersConcurrencyWaitFor($workerA);
    $resultB = ordersConcurrencyWaitFor($workerB);

    ordersConcurrencyAssertOk($resultA);
    ordersConcurrencyAssertOk($resultB);

    expect(Order::query()->findOrFail((int) $orderA->id)->total_minor)
        ->toBe((int) OrderItem::query()->where('order_id', (int) $orderA->id)->sum('total_minor'))
        ->and(Order::query()->findOrFail((int) $orderB->id)->total_minor)
        ->toBe((int) OrderItem::query()->where('order_id', (int) $orderB->id)->sum('total_minor'));
});

it('rechecks open status under lock after a concurrent cancellation', function (): void {
    foreach (['add_item', 'assign_waiter', 'add_subtable'] as $mode) {
        $record = ordersConcurrencyFixture();
        ordersConcurrencyActingIn($record);
        $order = app(OpenOrder::class)((int) $record['tables'][0]->id);
        $initialWaiterId = $order->waiter_id;
        $initialItemCount = OrderItem::query()->count();
        $initialSubtableCount = OrderSubtable::query()->count();
        $prefix = ordersConcurrencyPrefix("toc-{$mode}");
        $startFile = "{$prefix}.start";
        $payload = ordersConcurrencyPayload($record, $startFile, [
            'mode' => $mode,
            'order_id' => (int) $order->id,
            'menu_item_id' => (int) $record['menu_item']->id,
            'waiter_id' => null,
        ]);

        DB::beginTransaction();

        try {
            DB::statement('select id from orders where id = ? for update', [(int) $order->id]);

            $process = ordersConcurrencyStartWorker($payload);
            touch($startFile);
            usleep(400_000);

            DB::statement("update orders set status = 'cancelled', closed_at = now() where id = ?", [(int) $order->id]);
            DB::commit();
        } catch (QueryException $exception) {
            DB::rollBack();

            throw $exception;
        }

        $result = ordersConcurrencyWaitFor($process);
        $fresh = Order::query()->findOrFail((int) $order->id);

        expect($result['ok'])->toBeFalse()
            ->and($result['domain_code'])->toBe('orders.order_not_open')
            ->and($fresh->status)->toBe('cancelled')
            ->and($fresh->waiter_id)->toBe($initialWaiterId)
            ->and(OrderItem::query()->count())->toBe($initialItemCount)
            ->and(OrderSubtable::query()->count())->toBe($initialSubtableCount);
    }
});

it('holds the payable order lock across a caller owned transaction before item mutation continues', function (): void {
    $record = ordersConcurrencyFixture();
    ordersConcurrencyActingIn($record);
    $order = app(OpenOrder::class)((int) $record['tables'][0]->id);
    app(AddItem::class)((int) $order->id, (int) $record['menu_item']->id, 1);
    $prefix = ordersConcurrencyPrefix('payable-lock-add-item');
    $startFile = "{$prefix}.start";
    $backendPidFile = "{$prefix}.pid";

    DB::beginTransaction();

    try {
        $parentBackendPid = (int) DB::scalar('select pg_backend_pid()');
        $snapshot = app(PayableOrderReader::class)->lockPayableForUpdate((int) $order->id);

        expect($snapshot->totalMinor)->toBe(1000);

        $worker = ordersConcurrencyStartWorker(ordersConcurrencyPayload($record, $startFile, [
            'mode' => 'add_item',
            'order_id' => (int) $order->id,
            'menu_item_id' => (int) $record['menu_item']->id,
            'backend_pid_file' => $backendPidFile,
        ]));
        $workerBackendPid = ordersConcurrencyWaitForBackendPid($backendPidFile);

        touch($startFile);
        ordersConcurrencyWaitUntilBlockedBy($workerBackendPid, $parentBackendPid);

        DB::commit();
    } catch (Throwable $exception) {
        DB::rollBack();

        throw $exception;
    }

    $result = ordersConcurrencyWaitFor($worker);
    ordersConcurrencyAssertOk($result);

    expect((int) Order::query()->findOrFail((int) $order->id)->total_minor)->toBe(2000);
});

it('rejects payable order locking outside a caller owned transaction', function (): void {
    $record = ordersConcurrencyFixture();
    ordersConcurrencyActingIn($record);
    $order = app(OpenOrder::class)((int) $record['tables'][0]->id);
    app(AddItem::class)((int) $order->id, (int) $record['menu_item']->id, 1);
    $connection = DB::connection();

    expect($connection->transactionLevel())->toBe(0)
        ->and((string) Order::query()->whereKey((int) $order->id)->value('status'))->toBe('open')
        ->and((int) Order::query()->whereKey((int) $order->id)->value('total_minor'))->toBe(1000);

    try {
        app(PayableOrderReader::class)->lockPayableForUpdate((int) $order->id);
        $this->fail('Expected payable locking without a caller-owned transaction to fail.');
    } catch (OrdersDomainException $exception) {
        expect($exception->errorCode())->toBe('orders.payable_lock_requires_transaction')
            ->and($connection->transactionLevel())->toBe(0);
    }
});

it('rechecks payable state under lock after a concurrent cancellation', function (): void {
    $record = ordersConcurrencyFixture();
    ordersConcurrencyActingIn($record);
    $order = app(OpenOrder::class)((int) $record['tables'][0]->id);
    app(AddItem::class)((int) $order->id, (int) $record['menu_item']->id, 1);
    $prefix = ordersConcurrencyPrefix('payable-lock-cancel');
    $startFile = "{$prefix}.start";
    $backendPidFile = "{$prefix}.pid";

    DB::beginTransaction();

    try {
        $parentBackendPid = (int) DB::scalar('select pg_backend_pid()');
        DB::statement('select id from orders where id = ? for update', [(int) $order->id]);

        $worker = ordersConcurrencyStartWorker(ordersConcurrencyPayload($record, $startFile, [
            'mode' => 'lock_payable',
            'order_id' => (int) $order->id,
            'backend_pid_file' => $backendPidFile,
        ]));
        $workerBackendPid = ordersConcurrencyWaitForBackendPid($backendPidFile);

        touch($startFile);
        ordersConcurrencyWaitUntilBlockedBy($workerBackendPid, $parentBackendPid);

        DB::statement("update orders set status = 'cancelled', closed_at = now() where id = ?", [(int) $order->id]);
        DB::commit();
    } catch (Throwable $exception) {
        DB::rollBack();

        throw $exception;
    }

    $result = ordersConcurrencyWaitFor($worker);

    expect($result['ok'])->toBeFalse()
        ->and($result['domain_code'])->toBe('orders.order_not_open')
        ->and(Order::query()->findOrFail((int) $order->id)->status)->toBe('cancelled');
});

it('recovers from a real PostgreSQL deadlock within bounded transaction attempts', function (): void {
    $record = ordersConcurrencyFixture();
    ordersConcurrencyActingIn($record);
    $orderA = app(OpenOrder::class)((int) $record['tables'][0]->id);
    $orderB = app(OpenOrder::class)((int) $record['tables'][1]->id);
    $prefix = ordersConcurrencyPrefix('retry-deadlock');
    $startFile = "{$prefix}.start";
    $goFile = "{$prefix}.go";
    $readyA = "{$prefix}.a.ready";
    $readyB = "{$prefix}.b.ready";

    $workerA = ordersConcurrencyStartWorker(ordersConcurrencyPayload($record, $startFile, [
        'mode' => 'retry_deadlock',
        'first_order_id' => (int) $orderA->id,
        'second_order_id' => (int) $orderB->id,
        'ready_file' => $readyA,
        'go_file' => $goFile,
    ]));
    $workerB = ordersConcurrencyStartWorker(ordersConcurrencyPayload($record, $startFile, [
        'mode' => 'retry_deadlock',
        'first_order_id' => (int) $orderB->id,
        'second_order_id' => (int) $orderA->id,
        'ready_file' => $readyB,
        'go_file' => $goFile,
    ]));

    touch($startFile);
    ordersConcurrencyWaitForFile($readyA);
    ordersConcurrencyWaitForFile($readyB);
    touch($goFile);

    $resultA = ordersConcurrencyWaitFor($workerA);
    $resultB = ordersConcurrencyWaitFor($workerB);

    ordersConcurrencyAssertOk($resultA);
    ordersConcurrencyAssertOk($resultB);

    expect(max((int) $resultA['attempts'], (int) $resultB['attempts']))->toBeGreaterThan(1)
        ->and((int) $resultA['attempts'])->toBeLessThanOrEqual(3)
        ->and((int) $resultB['attempts'])->toBeLessThanOrEqual(3);
});

it('normalizes concurrent open-order occupancy to exactly one success and one domain failure', function (): void {
    $record = ordersConcurrencyFixture();
    $prefix = ordersConcurrencyPrefix('open-occupancy');
    $startFile = "{$prefix}.start";

    $workerA = ordersConcurrencyStartWorker(ordersConcurrencyPayload($record, $startFile, [
        'mode' => 'open_order',
        'table_id' => (int) $record['tables'][0]->id,
    ]));
    $workerB = ordersConcurrencyStartWorker(ordersConcurrencyPayload($record, $startFile, [
        'mode' => 'open_order',
        'table_id' => (int) $record['tables'][0]->id,
    ]));

    touch($startFile);

    $results = [ordersConcurrencyWaitFor($workerA), ordersConcurrencyWaitFor($workerB)];

    expect(ordersConcurrencyCountSuccesses($results))->toBe(1)
        ->and(ordersConcurrencyCountDomainCode($results, 'orders.table_already_open'))->toBe(1)
        ->and(Order::query()->where('table_id', (int) $record['tables'][0]->id)->where('status', 'open')->count())->toBe(1);
});

it('normalizes concurrent duplicate subtable creation to exactly one success and one domain failure', function (): void {
    $record = ordersConcurrencyFixture();
    ordersConcurrencyActingIn($record);
    $order = app(OpenOrder::class)((int) $record['tables'][0]->id);
    $prefix = ordersConcurrencyPrefix('subtable-duplicate');
    $startFile = "{$prefix}.start";

    $workerA = ordersConcurrencyStartWorker(ordersConcurrencyPayload($record, $startFile, [
        'mode' => 'add_subtable',
        'order_id' => (int) $order->id,
        'name' => ' Guest A ',
    ]));
    $workerB = ordersConcurrencyStartWorker(ordersConcurrencyPayload($record, $startFile, [
        'mode' => 'add_subtable',
        'order_id' => (int) $order->id,
        'name' => 'guest a',
    ]));

    touch($startFile);

    $results = [ordersConcurrencyWaitFor($workerA), ordersConcurrencyWaitFor($workerB)];

    expect(ordersConcurrencyCountSuccesses($results))->toBe(1)
        ->and(ordersConcurrencyCountDomainCode($results, 'orders.subtable_name_duplicate'))->toBe(1)
        ->and(OrderSubtable::query()->where('order_id', (int) $order->id)->where('status', 'open')->count())->toBe(1)
        ->and(mb_strtolower(trim((string) OrderSubtable::query()->where('order_id', (int) $order->id)->value('name'))))->toBe('guest a');
});

it('normalizes concurrent whole-order moves to the same target table to exactly one success and one domain failure', function (): void {
    $record = ordersConcurrencyFixture(tableCount: 3);
    ordersConcurrencyActingIn($record);
    $orderA = app(OpenOrder::class)((int) $record['tables'][0]->id);
    $orderB = app(OpenOrder::class)((int) $record['tables'][1]->id);
    $targetTableId = (int) $record['tables'][2]->id;
    $prefix = ordersConcurrencyPrefix('move-order-occupancy');
    $startFile = "{$prefix}.start";

    $workerA = ordersConcurrencyStartWorker(ordersConcurrencyPayload($record, $startFile, [
        'mode' => 'move_order',
        'order_id' => (int) $orderA->id,
        'target_table_id' => $targetTableId,
    ]));
    $workerB = ordersConcurrencyStartWorker(ordersConcurrencyPayload($record, $startFile, [
        'mode' => 'move_order',
        'order_id' => (int) $orderB->id,
        'target_table_id' => $targetTableId,
    ]));

    touch($startFile);

    $results = [ordersConcurrencyWaitFor($workerA), ordersConcurrencyWaitFor($workerB)];

    expect(ordersConcurrencyCountSuccesses($results))->toBe(1)
        ->and(ordersConcurrencyCountDomainCode($results, 'orders.table_already_open'))->toBe(1)
        ->and(Order::query()->where('table_id', $targetTableId)->where('status', 'open')->count())->toBe(1);
});

/**
 * @return array{tenant: Tenant, branch: Branch, user: User, tables: list<Table>, menu_item: MenuItem, other_menu_item: MenuItem}
 */
function ordersConcurrencyFixture(int $tableCount = 2): array
{
    $tenant = Tenant::query()->create([
        'name' => 'Concurrency Tenant',
        'slug' => 'concurrency-'.str()->random(8),
        'default_locale' => 'en',
        'currency' => 'AMD',
        'status' => 'active',
    ]);

    app(TenantResolver::class)->set((int) $tenant->id);

    $branch = Branch::query()->create([
        'name' => 'Concurrency Branch',
        'timezone' => 'Asia/Yerevan',
        'status' => 'active',
    ]);

    app(BranchContext::class)->set((int) $branch->id);

    $role = Role::query()->create([
        'code' => 'orders-concurrency-role-'.str()->random(8),
        'name' => 'Orders Concurrency Role',
    ]);

    $user = User::query()->create([
        'role_id' => (int) $role->id,
        'name' => 'Concurrency Manager',
        'email' => 'orders-concurrency-'.str()->random(8).'@smartrest.test',
        'username' => 'orders-concurrency-'.str()->random(8),
        'default_locale' => 'en',
        'active' => true,
        'is_superadmin' => false,
        'password' => Hash::make('password'),
    ]);

    UserBranchAssignment::query()->create([
        'user_id' => (int) $user->id,
        'branch_id' => (int) $branch->id,
    ]);

    $hall = Hall::query()->create([
        'branch_id' => (int) $branch->id,
        'translated_name' => ['hy' => 'Concurrency Hall', 'ru' => 'Concurrency Hall', 'en' => 'Concurrency Hall'],
        'color' => '#5FA8D3',
        'sort_order' => 10,
        'active' => true,
    ]);

    $tables = [];

    for ($index = 1; $index <= $tableCount; $index++) {
        $tables[] = Table::query()->create([
            'branch_id' => (int) $branch->id,
            'hall_id' => (int) $hall->id,
            'translated_name' => ['hy' => "Table {$index}", 'ru' => "Table {$index}", 'en' => "Table {$index}"],
            'type' => 'standard',
            'shape' => 'square',
            'hdm_department' => 1,
            'is_delivery' => false,
            'sort_order' => $index,
            'active' => true,
        ]);
    }

    $root = MenuCategory::query()->create([
        'translated_name' => ['hy' => 'Concurrency Root', 'ru' => 'Concurrency Root', 'en' => 'Concurrency Root'],
        'sort_order' => 0,
        'active' => true,
    ]);
    $category = MenuCategory::query()->create([
        'parent_id' => (int) $root->id,
        'translated_name' => ['hy' => 'Concurrency Category', 'ru' => 'Concurrency Category', 'en' => 'Concurrency Category'],
        'sort_order' => 10,
        'active' => true,
    ]);
    $menuItem = ordersConcurrencyMenuItem($branch, $category, 'Concurrency Item', 1000);
    $otherMenuItem = ordersConcurrencyMenuItem($branch, $category, 'Other Concurrency Item', 1500);

    return [
        'tenant' => $tenant,
        'branch' => $branch,
        'user' => $user,
        'tables' => $tables,
        'menu_item' => $menuItem,
        'other_menu_item' => $otherMenuItem,
    ];
}

function ordersConcurrencyMenuItem(Branch $branch, MenuCategory $category, string $name, int $priceMinor): MenuItem
{
    return MenuItem::query()->create([
        'branch_id' => (int) $branch->id,
        'category_id' => (int) $category->id,
        'translated_name' => ['hy' => $name, 'ru' => $name, 'en' => $name],
        'translated_description' => ['hy' => "{$name} Description", 'ru' => "{$name} Description", 'en' => "{$name} Description"],
        'price_minor' => $priceMinor,
        'currency' => 'AMD',
        'active' => true,
    ]);
}

/**
 * @param  array{tenant: Tenant, branch: Branch, user: User}  $record
 */
function ordersConcurrencyActingIn(array $record): void
{
    app(TenantResolver::class)->set((int) $record['tenant']->id);
    app(BranchContext::class)->set((int) $record['branch']->id);
    auth()->login($record['user']);
    LogContext::start('orders-concurrency-parent', 'orders');
}

/**
 * @param  array{tenant: Tenant, branch: Branch, user: User}  $record
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function ordersConcurrencyPayload(array $record, string $startFile, array $overrides): array
{
    return [
        'tenant_id' => (int) $record['tenant']->id,
        'branch_id' => (int) $record['branch']->id,
        'user_id' => (int) $record['user']->id,
        'request_id' => 'orders-concurrency-worker',
        'start_file' => $startFile,
    ] + $overrides;
}

/**
 * @param  array<string, mixed>  $payload
 */
function ordersConcurrencyStartWorker(array $payload): Process
{
    $encoded = base64_encode(json_encode($payload, JSON_THROW_ON_ERROR));
    $process = new Process([
        PHP_BINARY,
        base_path('tests/Support/Orders/concurrent_order_worker.php'),
        $encoded,
    ], base_path(), ordersConcurrencyProcessEnvironment());
    $process->setTimeout(15);
    $process->start();

    return $process;
}

/**
 * @return array<string, mixed>
 */
function ordersConcurrencyWaitFor(Process $process): array
{
    $process->wait();

    if (! $process->isSuccessful()) {
        throw new RuntimeException($process->getErrorOutput() ?: $process->getOutput());
    }

    $lines = array_values(array_filter(explode("\n", trim($process->getOutput()))));
    $lastLine = end($lines);

    if (! is_string($lastLine)) {
        throw new RuntimeException('Concurrency worker produced no output.');
    }

    $result = json_decode($lastLine, true, flags: JSON_THROW_ON_ERROR);

    if (! is_array($result)) {
        throw new RuntimeException('Concurrency worker produced invalid JSON.');
    }

    return $result;
}

function ordersConcurrencySetTimeouts(): void
{
    DB::statement("set lock_timeout = '1500ms'");
    DB::statement("set statement_timeout = '10000ms'");
}

/**
 * @return array<string, string>
 */
function ordersConcurrencyProcessEnvironment(): array
{
    $connection = config('database.connections.pgsql');

    return [
        'APP_ENV' => 'testing',
        'APP_KEY' => (string) config('app.key'),
        'BCRYPT_ROUNDS' => '4',
        'CACHE_STORE' => 'array',
        'DB_CONNECTION' => 'pgsql',
        'DB_DATABASE' => (string) ($connection['database'] ?? ''),
        'DB_HOST' => (string) ($connection['host'] ?? ''),
        'DB_PASSWORD' => (string) ($connection['password'] ?? ''),
        'DB_PORT' => (string) ($connection['port'] ?? '5432'),
        'DB_URL' => '',
        'DB_USERNAME' => (string) ($connection['username'] ?? ''),
        'QUEUE_CONNECTION' => 'sync',
        'SESSION_DRIVER' => 'array',
    ];
}

function ordersConcurrencyPrefix(string $name): string
{
    $directory = storage_path('framework/testing');

    if (! is_dir($directory)) {
        mkdir($directory, 0775, true);
    }

    return $directory.'/orders-concurrency-'.$name.'-'.bin2hex(random_bytes(6));
}

function ordersConcurrencyWaitForFile(string $path): void
{
    $deadline = microtime(true) + 8.0;

    while (! file_exists($path)) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException("Timed out waiting for {$path}.");
        }

        usleep(20_000);
    }
}

function ordersConcurrencyWaitForBackendPid(string $path): int
{
    ordersConcurrencyWaitForFile($path);

    $pid = trim((string) file_get_contents($path));

    if (! ctype_digit($pid)) {
        throw new RuntimeException("Invalid PostgreSQL backend pid in {$path}.");
    }

    return (int) $pid;
}

function ordersConcurrencyWaitUntilBlockedBy(int $blockedPid, int $blockingPid): void
{
    $deadline = microtime(true) + 8.0;

    do {
        $blockingPids = DB::selectOne('select pg_blocking_pids(?) as pids', [$blockedPid]);
        $pids = $blockingPids?->pids ?? '';
        $normalized = trim((string) $pids, '{}');
        $ids = $normalized === ''
            ? []
            : array_map(static fn (string $pid): int => (int) $pid, explode(',', $normalized));

        if (in_array($blockingPid, $ids, true)) {
            expect(true)->toBeTrue();

            return;
        }

        usleep(20_000);
    } while (microtime(true) <= $deadline);

    throw new RuntimeException("PostgreSQL backend {$blockedPid} was not blocked by {$blockingPid}.");
}

/**
 * @param  list<array<string, mixed>>  $results
 */
function ordersConcurrencyCountSuccesses(array $results): int
{
    return count(array_filter($results, static fn (array $result): bool => ($result['ok'] ?? false) === true));
}

/**
 * @param  list<array<string, mixed>>  $results
 */
function ordersConcurrencyCountDomainCode(array $results, string $code): int
{
    return count(array_filter($results, static fn (array $result): bool => ($result['domain_code'] ?? null) === $code));
}

/**
 * @param  array<string, mixed>  $result
 */
function ordersConcurrencyAssertOk(array $result): void
{
    if (($result['ok'] ?? false) === true) {
        expect(true)->toBeTrue();

        return;
    }

    throw new RuntimeException(json_encode($result, JSON_THROW_ON_ERROR));
}
