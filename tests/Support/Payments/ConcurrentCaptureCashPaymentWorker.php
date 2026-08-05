<?php

declare(strict_types=1);

namespace Tests\Support\Payments;

use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Payments\Application\CaptureCashPayment;
use App\Modules\Payments\Application\CaptureCashPaymentCommand;
use App\Modules\Payments\Application\DeactivateCashbox;
use App\Modules\Payments\Domain\PaymentsDomainException;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;
use App\Support\Logging\LogContext;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

final class ConcurrentCaptureCashPaymentWorker
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
            self::writeReadyFile($payload);
            self::waitForFile((string) $payload['start_file']);

            $result = self::runMode($payload);
            self::writeResult(['ok' => true] + $result);
        } catch (PaymentsDomainException $exception) {
            self::writeDomainFailure($exception);
        } catch (QueryException $exception) {
            self::writeResult([
                'ok' => false,
                'query_code' => (string) $exception->getCode(),
                'message' => $exception->getMessage(),
                'transaction_level' => DB::connection()->transactionLevel(),
            ]);
        } catch (Throwable $exception) {
            if (method_exists($exception, 'errorCode')) {
                self::writeDomainFailure($exception);

                return 0;
            }

            self::writeResult([
                'ok' => false,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'transaction_level' => DB::connection()->transactionLevel(),
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
            'capture_cash_payment' => self::captureCashPayment($payload),
            'deactivate_cashbox' => self::deactivateCashbox($payload),
            default => throw new \InvalidArgumentException('Unknown worker mode.'),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private static function captureCashPayment(array $payload): array
    {
        $result = app(CaptureCashPayment::class)(new CaptureCashPaymentCommand(
            orderId: (int) $payload['order_id'],
            cashboxId: (int) $payload['cashbox_id'],
            expectedAmountMinor: (int) $payload['expected_amount_minor'],
            expectedCurrency: (string) $payload['expected_currency'],
            idempotencyKey: (string) $payload['idempotency_key'],
        ));

        return [
            'payment_id' => $result->paymentId,
            'payment_allocation_id' => $result->paymentAllocationId,
            'cashbox_entry_id' => $result->cashboxEntryId,
            'tenant_id' => $result->tenantId,
            'branch_id' => $result->branchId,
            'order_id' => $result->orderId,
            'cashbox_id' => $result->cashboxId,
            'amount_minor' => $result->amountMinor,
            'currency' => $result->currency,
            'idempotency_key' => $result->idempotencyKey,
            'idempotency_fingerprint' => $result->idempotencyFingerprint,
            'replayed' => $result->replayed,
            'transaction_level' => DB::connection()->transactionLevel(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private static function deactivateCashbox(array $payload): array
    {
        $cashbox = app(DeactivateCashbox::class)(
            (int) $payload['cashbox_id'],
            isset($payload['replacement_default_id']) ? (int) $payload['replacement_default_id'] : null,
        );

        return [
            'cashbox_id' => (int) $cashbox->id,
            'is_active' => (bool) $cashbox->is_active,
            'is_default' => (bool) $cashbox->is_default,
            'transaction_level' => DB::connection()->transactionLevel(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function configureSession(array $payload): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            throw new \RuntimeException('Capture payment concurrency worker requires PostgreSQL.');
        }

        DB::statement("set lock_timeout = '1500ms'");
        DB::statement("set statement_timeout = '10000ms'");

        if (isset($payload['backend_pid_file'])) {
            file_put_contents((string) $payload['backend_pid_file'], (string) DB::scalar('select pg_backend_pid()'));
        }

        app(TenantResolver::class)->set((int) $payload['tenant_id']);
        app(BranchContext::class)->set((int) $payload['branch_id']);

        if (isset($payload['user_id'])) {
            $user = User::query()->find((int) $payload['user_id']);

            if ($user instanceof User) {
                Auth::login($user);
            }
        }

        LogContext::start((string) ($payload['request_id'] ?? 'payments-capture-concurrency-worker'), 'payments');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function writeReadyFile(array $payload): void
    {
        if (isset($payload['ready_file'])) {
            file_put_contents((string) $payload['ready_file'], 'ready');
        }
    }

    private static function writeDomainFailure(Throwable $exception): void
    {
        self::writeResult([
            'ok' => false,
            'domain_code' => method_exists($exception, 'errorCode') ? $exception->errorCode() : null,
            'message' => $exception->getMessage(),
            'transaction_level' => DB::connection()->transactionLevel(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function encodePayload(array $payload): string
    {
        return base64_encode(json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    public static function command(array $payload): array
    {
        return [
            PHP_BINARY,
            dirname(__DIR__).'/Payments/concurrent_capture_cash_payment_worker.php',
            self::encodePayload($payload),
        ];
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
