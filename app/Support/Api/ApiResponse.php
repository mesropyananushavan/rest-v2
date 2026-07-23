<?php

declare(strict_types=1);

namespace App\Support\Api;

use App\Support\Logging\LogContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\App;

final class ApiResponse
{
    /**
     * @param  array<string, mixed>|list<array<string, mixed>>  $data
     * @param  array<string, mixed>  $meta
     */
    public static function success(Request $request, array $data, array $meta = [], int $status = 200): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'meta' => [
                'request_id' => self::requestId($request),
                'locale' => App::getLocale(),
            ] + $meta,
            'errors' => [],
        ], $status);
    }

    /**
     * @param  list<array{code: string, message: string, field: string|null}>  $errors
     */
    public static function errors(Request $request, array $errors, int $status): JsonResponse
    {
        return response()->json([
            'errors' => $errors,
            'meta' => [
                'request_id' => self::requestId($request),
            ],
        ], $status);
    }

    public static function error(Request $request, string $code, string $message, ?string $field, int $status): JsonResponse
    {
        return self::errors($request, [[
            'code' => $code,
            'message' => $message,
            'field' => $field,
        ]], $status);
    }

    /**
     * @template TKey of array-key
     * @template TValue
     *
     * @param  LengthAwarePaginator<TKey, TValue>  $paginator
     * @return array{current_page: int, per_page: int, total: int, last_page: int, from: int|null, to: int|null, has_more_pages: bool}
     */
    public static function pagination(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
            'has_more_pages' => $paginator->hasMorePages(),
        ];
    }

    /**
     * @return array{current_page: int, per_page: int, total: int, last_page: int, from: null, to: null, has_more_pages: false}
     */
    public static function emptyPagination(int $page, int $perPage): array
    {
        return [
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => 0,
            'last_page' => 1,
            'from' => null,
            'to' => null,
            'has_more_pages' => false,
        ];
    }

    private static function requestId(Request $request): string
    {
        $requestId = $request->attributes->get('request_id');

        if (is_string($requestId) && $requestId !== '') {
            return $requestId;
        }

        $current = LogContext::current()['request_id'];

        return is_string($current) && $current !== '' ? $current : LogContext::newRequestId();
    }
}
