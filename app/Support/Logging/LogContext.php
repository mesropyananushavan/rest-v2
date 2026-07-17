<?php

declare(strict_types=1);

namespace App\Support\Logging;

use App\Modules\Tenancy\Contracts\BranchContext;
use App\Modules\Tenancy\Contracts\TenantResolver;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final class LogContext
{
    /** @var array{request_id: string|null, tenant_id: int|null, branch_id: int|null, user_id: int|null, module: string|null} */
    private static array $context = [
        'request_id' => null,
        'tenant_id' => null,
        'branch_id' => null,
        'user_id' => null,
        'module' => null,
    ];

    public static function start(?string $requestId = null, ?string $module = null): string
    {
        self::$context['request_id'] = $requestId ?: self::newRequestId();
        self::$context['module'] = $module;
        self::refreshRuntimeContext();
        self::share();

        return (string) self::$context['request_id'];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function restore(array $context): void
    {
        self::$context = array_replace(self::$context, [
            'request_id' => self::stringOrNull($context['request_id'] ?? null),
            'tenant_id' => self::intOrNull($context['tenant_id'] ?? null),
            'branch_id' => self::intOrNull($context['branch_id'] ?? null),
            'user_id' => self::intOrNull($context['user_id'] ?? null),
            'module' => self::stringOrNull($context['module'] ?? null),
        ]);

        self::share();
    }

    public static function refreshRuntimeContext(?string $module = null): void
    {
        self::$context['tenant_id'] = self::tenantId();
        self::$context['branch_id'] = self::branchId();
        self::$context['user_id'] = self::userId();

        if ($module !== null) {
            self::$context['module'] = $module;
        }

        self::share();
    }

    /**
     * @return array{request_id: string|null, tenant_id: int|null, branch_id: int|null, user_id: int|null, module: string|null}
     */
    public static function current(): array
    {
        self::refreshRuntimeContext();

        return self::$context;
    }

    public static function share(): void
    {
        Log::shareContext(self::$context);
    }

    public static function clear(): void
    {
        self::$context = [
            'request_id' => null,
            'tenant_id' => null,
            'branch_id' => null,
            'user_id' => null,
            'module' => null,
        ];

        Log::shareContext(self::$context);
    }

    public static function newRequestId(): string
    {
        return (string) Str::uuid();
    }

    private static function tenantId(): ?int
    {
        try {
            return app(TenantResolver::class)->id();
        } catch (Throwable) {
            return null;
        }
    }

    private static function branchId(): ?int
    {
        try {
            return app(BranchContext::class)->id();
        } catch (Throwable) {
            return null;
        }
    }

    private static function userId(): ?int
    {
        $id = Auth::id();

        return is_numeric($id) ? (int) $id : null;
    }

    private static function intOrNull(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private static function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
