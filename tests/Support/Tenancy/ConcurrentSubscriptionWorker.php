<?php

declare(strict_types=1);

namespace Tests\Support\Tenancy;

use App\Modules\Tenancy\Application\RecordTenantSubscriptionPayment;
use App\Modules\Tenancy\Application\SuspendOverdueTenantSubscriptions;
use App\Modules\Tenancy\Domain\TenancyDomainException;
use App\Support\Logging\LogContext;
use DateTimeImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Throwable;

final class ConcurrentSubscriptionWorker
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
            self::touchReadyFile($payload);

            $result = self::runMode($payload);
            self::writeResult(['ok' => true] + $result);
        } catch (TenancyDomainException $exception) {
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
            'auto_suspend' => self::autoSuspend($payload),
            'record_payment' => self::recordPayment($payload),
            default => throw new \InvalidArgumentException('Unknown worker mode.'),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private static function autoSuspend(array $payload): array
    {
        $result = app(SuspendOverdueTenantSubscriptions::class)(new DateTimeImmutable((string) $payload['now']));

        return [
            'candidate_count' => $result->candidateCount,
            'suspended_count' => $result->suspendedCount,
            'skipped_not_serviceable_count' => $result->skippedNotServiceableCount,
            'skipped_no_longer_suspendable_count' => $result->skippedNoLongerSuspendableCount,
            'skipped_already_suspended_count' => $result->skippedAlreadySuspendedCount,
            'skipped_unknown_tenant_count' => $result->skippedUnknownTenantCount,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private static function recordPayment(array $payload): array
    {
        $subscription = app(RecordTenantSubscriptionPayment::class)(
            (int) $payload['tenant_id'],
            new DateTimeImmutable((string) $payload['payment_date']),
            new DateTimeImmutable((string) $payload['expected_next_due_on']),
        );

        return [
            'subscription_id' => (int) $subscription->id,
            'next_due_on' => self::dateValue($subscription->next_due_on),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function configureSession(array $payload): void
    {
        DB::statement("set lock_timeout = '15000ms'");
        DB::statement("set statement_timeout = '10000ms'");
        DB::statement('select set_config(?, ?, false)', ['application_name', (string) $payload['application_name']]);

        LogContext::start((string) ($payload['request_id'] ?? 'subscription-concurrency-worker'), 'tenancy');
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
     * @param  array<string, mixed>  $payload
     */
    private static function touchReadyFile(array $payload): void
    {
        if (! isset($payload['ready_file'])) {
            return;
        }

        touch((string) $payload['ready_file']);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private static function writeResult(array $result): void
    {
        fwrite(STDOUT, json_encode($result, JSON_THROW_ON_ERROR).PHP_EOL);
    }

    private static function dateValue(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_string($value) && $value !== '') {
            return $value;
        }

        return null;
    }
}
