<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Logging\LogContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AttachLogContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = LogContext::start(
            $this->requestId($request),
            $this->module($request),
        );

        $request->attributes->set('request_id', $requestId);

        try {
            $response = $next($request);

            assert($response instanceof Response);
            $response->headers->set('X-Request-Id', $requestId);

            return $response;
        } finally {
            LogContext::clear();
        }
    }

    private function requestId(Request $request): ?string
    {
        $requestId = $request->headers->get('X-Request-Id');

        if (! is_string($requestId) || $requestId === '') {
            return null;
        }

        return preg_match('/^[A-Za-z0-9._:-]{1,128}$/', $requestId) === 1 ? $requestId : null;
    }

    private function module(Request $request): ?string
    {
        $uses = $request->route()?->getAction('uses');

        if (! is_string($uses)) {
            return null;
        }

        preg_match('/App\\\\Modules\\\\([A-Za-z0-9_]+)\\\\/', $uses, $matches);

        return $matches[1] ?? null;
    }
}
