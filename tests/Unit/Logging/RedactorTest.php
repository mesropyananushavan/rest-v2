<?php

declare(strict_types=1);

use App\Support\Logging\Redactor;

it('redacts sensitive values recursively', function (): void {
    expect(Redactor::context([
        'username' => 'manager',
        'password' => 'secret',
        'nested' => [
            'refresh_token' => 'token-value',
            'safe' => 'visible',
        ],
    ]))->toBe([
        'username' => 'manager',
        'password' => '[redacted]',
        'nested' => [
            'refresh_token' => '[redacted]',
            'safe' => 'visible',
        ],
    ]);
});
