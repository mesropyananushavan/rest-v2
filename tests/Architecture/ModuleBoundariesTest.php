<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

it('contains only the current module directories', function (): void {
    $modulesPath = app_path('Modules');

    $modules = collect(File::directories($modulesPath))
        ->map(fn (string $path): string => basename($path))
        ->sort()
        ->values()
        ->all();

    expect($modules)->toBe(['Identity', 'Menu', 'Tables', 'Tenancy']);
});

foreach (['Tenancy', 'Identity', 'Menu', 'Tables'] as $module) {
    $otherModules = collect(['Tenancy', 'Identity', 'Menu', 'Tables'])
        ->reject(fn (string $candidate): bool => $candidate === $module);

    $forbiddenNamespaces = $otherModules
        ->flatMap(fn (string $candidate): array => [
            "App\Modules\\{$candidate}\\Domain",
            "App\Modules\\{$candidate}\\Application",
            "App\Modules\\{$candidate}\\Infrastructure",
            "App\Modules\\{$candidate}\\Http",
        ])
        ->values()
        ->all();

    arch("{$module} internals do not reference other module internals")
        ->expect("App\Modules\\{$module}")
        ->not->toUse($forbiddenNamespaces);
}
