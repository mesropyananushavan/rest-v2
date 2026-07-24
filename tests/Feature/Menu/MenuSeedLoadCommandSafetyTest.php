<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

afterEach(function (): void {
    app()->detectEnvironment(fn (): string => 'testing');
    config(['database.default' => 'sqlite']);
});

it('does not let force bypass the local and testing environment guard', function (): void {
    app()->detectEnvironment(fn (): string => 'production');

    $this->artisan('menu:seed-load', [
        '--force' => true,
    ])
        ->expectsOutput('Refusing to run outside local/testing environments.')
        ->assertFailed();
});

it('blocks schema recreation against a non local database even with force', function (): void {
    menuSeedLoadPgsqlConfig(host: 'production-postgres', database: 'smartrest');

    $this->artisan('menu:seed-load', [
        '--fresh' => true,
        '--force' => true,
    ])
        ->expectsOutputToContain('Refusing destructive --fresh schema recreation: expected a local SmartRest database connection')
        ->assertFailed();
});

it('requires confirmation before schema recreation unless force suppresses it', function (): void {
    menuSeedLoadPgsqlConfig(host: 'postgres', database: 'smartrest');

    $this->artisan('menu:seed-load', [
        '--fresh' => true,
        '--restaurants' => 1,
        '--categories' => 1,
        '--subcategories' => 1,
        '--items' => 1,
        '--batch' => 5000,
    ])
        ->expectsConfirmation('This will delete the entire local database, including demo tenants. Continue?', 'no')
        ->expectsOutputToContain('Fresh load cleanup cancelled before recreating the local schema.')
        ->assertFailed();
});

function menuSeedLoadPgsqlConfig(string $host, string $database): void
{
    config([
        'database.default' => 'pgsql',
        'database.connections.pgsql.driver' => 'pgsql',
        'database.connections.pgsql.host' => $host,
        'database.connections.pgsql.database' => $database,
        'database.connections.pgsql.username' => 'smartrest',
        'database.connections.pgsql.password' => 'smartrest',
    ]);
}
