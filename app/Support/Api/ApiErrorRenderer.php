<?php

declare(strict_types=1);

namespace App\Support\Api;

use App\Modules\Menu\Domain\MenuDomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ApiErrorRenderer
{
    public static function register(Exceptions $exceptions): void
    {
        $exceptions->render(fn (AuthenticationException $exception, Request $request): ?JsonResponse => self::forApi(
            $request,
            fn (): JsonResponse => ApiResponse::error($request, 'auth.unauthenticated', __('api.errors.unauthenticated'), null, 401),
        ));

        $exceptions->render(fn (AuthorizationException $exception, Request $request): ?JsonResponse => self::forApi(
            $request,
            fn (): JsonResponse => ApiResponse::error($request, 'auth.forbidden', __('api.errors.forbidden'), null, 403),
        ));
        $exceptions->render(fn (AccessDeniedHttpException $exception, Request $request): ?JsonResponse => self::forApi(
            $request,
            fn (): JsonResponse => ApiResponse::error($request, 'auth.forbidden', __('api.errors.forbidden'), null, 403),
        ));

        $exceptions->render(fn (ModelNotFoundException $exception, Request $request): ?JsonResponse => self::notFound($request));
        $exceptions->render(fn (NotFoundHttpException $exception, Request $request): ?JsonResponse => self::notFound($request));

        $exceptions->render(fn (ValidationException $exception, Request $request): ?JsonResponse => self::forApi(
            $request,
            fn (): JsonResponse => ApiResponse::errors($request, self::validationErrors($exception), 422),
        ));
    }

    public static function menuDomain(MenuDomainException $exception, Request $request): JsonResponse
    {
        return ApiResponse::error($request, $exception->errorCode(), __($exception->errorCode()), null, 422);
    }

    private static function notFound(Request $request): ?JsonResponse
    {
        return self::forApi(
            $request,
            fn (): JsonResponse => ApiResponse::error($request, 'resource.not_found', __('api.errors.not_found'), null, 404),
        );
    }

    /**
     * @return list<array{code: string, message: string, field: string|null}>
     */
    private static function validationErrors(ValidationException $exception): array
    {
        /** @var array<string, array<string, mixed>> $failed */
        $failed = $exception->validator->failed();
        /** @var array<string, list<string>> $messagesByField */
        $messagesByField = $exception->errors();
        $errors = [];

        foreach ($messagesByField as $field => $messages) {
            $rule = array_key_first($failed[$field] ?? []) ?? 'invalid';
            $message = $messages[0] ?? __('api.errors.validation');

            $errors[] = [
                'code' => 'validation.'.Str::snake($rule),
                'message' => $message,
                'field' => $field,
            ];
        }

        return $errors;
    }

    /**
     * @param  callable(): JsonResponse  $render
     */
    private static function forApi(Request $request, callable $render): ?JsonResponse
    {
        if (! $request->expectsJson() && ! $request->is('api/*')) {
            return null;
        }

        return $render();
    }
}
