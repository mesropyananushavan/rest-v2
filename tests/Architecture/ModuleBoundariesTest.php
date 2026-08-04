<?php

declare(strict_types=1);

use App\Modules\Orders\Application\ReadPayableOrder;
use App\Modules\Orders\Contracts\PayableOrderReader;
use App\Modules\Orders\Contracts\PayableOrderSnapshot;
use Illuminate\Support\Facades\File;

it('contains only the current module directories', function (): void {
    $modulesPath = app_path('Modules');

    $modules = collect(File::directories($modulesPath))
        ->map(fn (string $path): string => basename($path))
        ->sort()
        ->values()
        ->all();

    expect($modules)->toBe(['Identity', 'Menu', 'Orders', 'Payments', 'Tables', 'Tenancy']);
});

foreach (['Tenancy', 'Identity', 'Menu', 'Orders', 'Payments', 'Tables'] as $module) {
    $otherModules = collect(['Tenancy', 'Identity', 'Menu', 'Orders', 'Payments', 'Tables'])
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

it('keeps Orders dependency on Identity limited to contracts', function (): void {
    $ordersSources = collect(File::allFiles(app_path('Modules/Orders')))
        ->map(fn (SplFileInfo $file): string => $file->getContents())
        ->implode("\n");

    expect($ordersSources)->toContain('App\Modules\Identity\Contracts\UserDirectory')
        ->and($ordersSources)->not->toContain('App\Modules\Identity\Domain')
        ->and($ordersSources)->not->toContain('App\Modules\Identity\Application')
        ->and($ordersSources)->not->toContain('App\Modules\Identity\Infrastructure')
        ->and($ordersSources)->not->toContain('App\Modules\Identity\Http');
});

it('keeps Orders free of direct Payments infrastructure dependencies', function (): void {
    $ordersSources = collect(File::allFiles(app_path('Modules/Orders')))
        ->map(fn (SplFileInfo $file): string => $file->getContents())
        ->implode("\n");

    expect($ordersSources)->not->toContain('App\Modules\Payments\Infrastructure')
        ->and($ordersSources)->not->toContain('App\Modules\Payments\Http')
        ->and($ordersSources)->not->toContain('cashboxes')
        ->and($ordersSources)->not->toContain('payments.cashboxes');
});

it('keeps future Payments to Orders dependency limited to Orders public contracts', function (): void {
    $paymentsSources = collect(File::allFiles(app_path('Modules/Payments')))
        ->map(fn (SplFileInfo $file): string => $file->getContents())
        ->implode("\n");

    expect($paymentsSources)->not->toContain('App\Modules\Orders\Domain')
        ->and($paymentsSources)->not->toContain('App\Modules\Orders\Application')
        ->and($paymentsSources)->not->toContain('App\Modules\Orders\Infrastructure')
        ->and($paymentsSources)->not->toContain('App\Modules\Orders\Http');
});

it('keeps payable order foundation owned by Orders contracts', function (): void {
    expect(interface_exists(PayableOrderReader::class))->toBeTrue()
        ->and(class_exists(PayableOrderSnapshot::class))->toBeTrue()
        ->and(app(PayableOrderReader::class))->toBeInstanceOf(ReadPayableOrder::class);
});
