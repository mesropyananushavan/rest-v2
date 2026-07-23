<?php

declare(strict_types=1);

use App\Modules\Menu\Http\Controllers\Api\MenuItemIndexController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware(['web', 'auth', 'tenant', 'branch', 'throttle:60,1'])
    ->group(function (): void {
        Route::get('/menu-items', MenuItemIndexController::class)
            ->middleware('can:menu.items.manage')
            ->name('api.v1.menu-items.index');
    });
