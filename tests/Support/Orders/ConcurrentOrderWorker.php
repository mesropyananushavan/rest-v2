<?php

declare(strict_types=1);

namespace Tests\Support\Orders;

use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Orders\Application\AddItem;
use App\Modules\Orders\Application\AddSubtable;
use App\Modules\Orders\Application\AssignWaiter;
use App\Modules\Orders\Application\ChangeItemQty;
use App\Modules\Orders\Application\MoveItem;
use App\Modules\Orders\Application\MoveOrder;
use App\Modules\Orders\Application\OpenOrder;
use App\Modules\Orders\Application\RemoveItem;
use App\Modules\Orders\Domain\OrdersDomainException;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Support\Logging\LogContext;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

final class ConcurrentOrderWorker
{
    /**
     * @param  list<string>  $arguments
     */
    public static function main(array $arguments): int
    {
        $payload = self::decodePayload($arguments[1] ?? '');
        $app = self::bootstrap();

        try {
            self::configureSession($payload);
            self::waitForFile((string) $payload['start_file']);

            $result = self::runMode($payload);
            self::writeResult(['ok' => true] + $result);
        } catch (OrdersDomainException $exception) {
            self::writeResult([
                'ok' => false,
                'domain_code' => $exception->errorCode(),
                'message' => $exception->getMessage(),
            ]);
        } catch (QueryException $exception) {
            self::writeResult([
                'ok' => false,
                'query_code' => (string) $exception->getCode(),
                'message' => $exception->getMessage(),
            ]);
        } catch (Throwable $exception) {
            self::writeResult([
                'ok' => false,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        } finally {
            $app->flush();
        }

        return 0;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private static function runMode(array $payload): array
    {
        return match ((string) $payload['mode']) {
            'add_item' => self::addItem($payload),
            'change_qty' => self::changeQty($payload),
            'remove_item' => self::removeItem($payload),
            'move_item' => self::moveItem($payload),
            'assign_waiter' => self::assignWaiter($payload),
            'add_subtable' => self::addSubtable($payload),
            'open_order' => self::openOrder($payload),
            'move_order' => self::moveOrder($payload),
            'retry_deadlock' => self::retryDeadlock($payload),
            default => throw new \InvalidArgumentException('Unknown worker mode.'),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private static function addItem(array $payload): array
    {
        $item = app(AddItem::class)((int) $payload['order_id'], (int) $payload['menu_item_id'], 1);

        return ['order_item_id' => (int) $item->id];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private static function changeQty(array $payload): array
    {
        $item = app(ChangeItemQty::class)((int) $payload['order_item_id'], (int) ($payload['qty'] ?? 2));

        return ['order_item_id' => (int) $item->id];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private static function removeItem(array $payload): array
    {
        $order = app(RemoveItem::class)((int) $payload['order_item_id']);

        return ['order_id' => (int) $order->id];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private static function moveItem(array $payload): array
    {
        $item = app(MoveItem::class)(
            (int) $payload['order_item_id'],
            isset($payload['target_order_id']) ? (int) $payload['target_order_id'] : null,
            isset($payload['target_subtable_id']) ? (int) $payload['target_subtable_id'] : null,
        );

        return [
            'order_item_id' => (int) $item->id,
            'order_id' => (int) $item->order_id,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private static function assignWaiter(array $payload): array
    {
        $order = app(AssignWaiter::class)((int) $payload['order_id'], isset($payload['waiter_id']) ? (int) $payload['waiter_id'] : null);

        return ['order_id' => (int) $order->id];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private static function addSubtable(array $payload): array
    {
        $subtable = app(AddSubtable::class)((int) $payload['order_id'], (string) ($payload['name'] ?? 'Concurrent guest'));

        return ['subtable_id' => (int) $subtable->id];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private static function openOrder(array $payload): array
    {
        $order = app(OpenOrder::class)((int) $payload['table_id']);

        return ['order_id' => (int) $order->id];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private static function moveOrder(array $payload): array
    {
        $order = app(MoveOrder::class)((int) $payload['order_id'], (int) $payload['target_table_id']);

        return [
            'order_id' => (int) $order->id,
            'table_id' => (int) $order->table_id,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private static function retryDeadlock(array $payload): array
    {
        $attempts = 0;
        $firstOrderId = (int) $payload['first_order_id'];
        $secondOrderId = (int) $payload['second_order_id'];
        $readyFile = (string) $payload['ready_file'];
        $goFile = (string) $payload['go_file'];

        app(OrderTransactionProbe::class)->run(function () use (&$attempts, $firstOrderId, $goFile, $readyFile, $secondOrderId): void {
            $attempts++;

            DB::statement('select id from orders where id = ? for update', [$firstOrderId]);

            if ($attempts === 1) {
                touch($readyFile);
                self::waitForFile($goFile);
            }

            DB::statement('select id from orders where id = ? for update', [$secondOrderId]);
        });

        return ['attempts' => $attempts];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function configureSession(array $payload): void
    {
        DB::statement("set lock_timeout = '1500ms'");
        DB::statement("set statement_timeout = '10000ms'");

        app(TenantResolver::class)->set((int) $payload['tenant_id']);
        app(BranchContext::class)->set((int) $payload['branch_id']);

        if (isset($payload['user_id'])) {
            $user = User::query()->find((int) $payload['user_id']);

            if ($user instanceof User) {
                Auth::login($user);
            }
        }

        LogContext::start((string) ($payload['request_id'] ?? 'orders-concurrency-worker'), 'orders');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function decodePayload(string $encoded): array
    {
        $decoded = base64_decode($encoded, true);

        if ($decoded === false) {
            throw new \InvalidArgumentException('Invalid worker payload encoding.');
        }

        $payload = json_decode($decoded, true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($payload)) {
            throw new \InvalidArgumentException('Invalid worker payload.');
        }

        return $payload;
    }

    private static function bootstrap(): Application
    {
        $basePath = dirname(__DIR__, 3);

        require_once $basePath.'/vendor/autoload.php';

        /** @var Application $app */
        $app = require $basePath.'/bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    private static function waitForFile(string $path): void
    {
        $deadline = microtime(true) + 8.0;

        while (! file_exists($path)) {
            if (microtime(true) > $deadline) {
                throw new \RuntimeException("Timed out waiting for {$path}.");
            }

            usleep(20_000);
        }
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private static function writeResult(array $result): void
    {
        fwrite(STDOUT, json_encode($result, JSON_THROW_ON_ERROR).PHP_EOL);
    }
}
