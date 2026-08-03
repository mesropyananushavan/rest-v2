<?php

declare(strict_types=1);

use Illuminate\Support\Env;

it('defaults PostgreSQL runtime configuration to the restricted application role', function (): void {
    $repository = Env::getRepository();
    $previousUsername = $repository->get('DB_USERNAME');
    $previousEnv = $_ENV['DB_USERNAME'] ?? null;
    $previousServer = $_SERVER['DB_USERNAME'] ?? null;
    $previousProcessEnv = getenv('DB_USERNAME');

    $repository->clear('DB_USERNAME');
    unset($_ENV['DB_USERNAME'], $_SERVER['DB_USERNAME']);
    putenv('DB_USERNAME');

    try {
        $database = require base_path('config/database.php');

        expect($database['connections']['pgsql']['username'])->toBe('smartrest_app')
            ->and($database['connections']['mysql']['username'])->toBe('smartrest');
    } finally {
        if ($previousUsername === null) {
            $repository->clear('DB_USERNAME');
        } else {
            $repository->set('DB_USERNAME', $previousUsername);
        }

        if ($previousEnv === null) {
            unset($_ENV['DB_USERNAME']);
        } else {
            $_ENV['DB_USERNAME'] = $previousEnv;
        }

        if ($previousServer === null) {
            unset($_SERVER['DB_USERNAME']);
        } else {
            $_SERVER['DB_USERNAME'] = $previousServer;
        }

        if ($previousProcessEnv === false) {
            putenv('DB_USERNAME');
        } else {
            putenv("DB_USERNAME={$previousProcessEnv}");
        }
    }
});
