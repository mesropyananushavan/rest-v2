<?php

declare(strict_types=1);

use App\Support\Logging\LogContext;
use Illuminate\Support\Facades\Route;

it('accepts and returns a request id while sharing log context', function (): void {
    Route::middleware('web')->get('/_test/log-context', fn () => response()->json(LogContext::current()));

    $this->withHeader('X-Request-Id', 'demo-request-123')
        ->get('/_test/log-context')
        ->assertOk()
        ->assertHeader('X-Request-Id', 'demo-request-123')
        ->assertJson([
            'request_id' => 'demo-request-123',
            'tenant_id' => null,
            'branch_id' => null,
            'user_id' => null,
            'module' => null,
        ]);
});

it('generates a request id when the header is absent', function (): void {
    Route::middleware('web')->get('/_test/generated-log-context', fn () => response()->json(LogContext::current()));

    $response = $this->get('/_test/generated-log-context')
        ->assertOk()
        ->assertHeader('X-Request-Id');

    expect($response->headers->get('X-Request-Id'))->toBeString()
        ->and($response->json('request_id'))->toBe($response->headers->get('X-Request-Id'));
});
