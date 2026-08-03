<?php

declare(strict_types=1);

namespace Tests\Support\Payments;

use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Payments\Application\CreateCashbox;
use App\Modules\Payments\Application\SelectDefaultCashbox;
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

final class ConcurrentCashboxWorker
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
        } catch (PaymentsDomainException $exception) {
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
            'create_cashbox' => self::createCashbox($payload),
            'select_default' => self::selectDefault($payload),
            default => throw new \InvalidArgumentException('Unknown worker mode.'),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private static function createCashbox(array $payload): array
    {
        $cashbox = app(CreateCashbox::class)((string) $payload['name']);

        return [
            'cashbox_id' => (int) $cashbox->id,
            'is_default' => (bool) $cashbox->is_default,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private static function selectDefault(array $payload): array
    {
        $cashbox = app(SelectDefaultCashbox::class)((int) $payload['cashbox_id']);

        return [
            'cashbox_id' => (int) $cashbox->id,
            'is_default' => (bool) $cashbox->is_default,
        ];
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

        LogContext::start((string) ($payload['request_id'] ?? 'cashbox-concurrency-worker'), 'payments');
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
            dirname(__DIR__).'/Payments/concurrent_cashbox_worker.php',
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
