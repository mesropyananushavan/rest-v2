<?php

declare(strict_types=1);

use App\Http\Controllers\AdminBranchSwitchController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminLocaleSwitchController;
use App\Modules\Identity\Http\Controllers\LoginController;
use App\Modules\Identity\Http\Controllers\LogoutController;
use App\Modules\Menu\Http\Controllers\MenuCategoryController;
use App\Modules\Menu\Http\Controllers\MenuIndexController;
use App\Modules\Menu\Http\Controllers\MenuItemController;
use App\Modules\Tenancy\Http\Controllers\BranchShowController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('login.store');
});

Route::post('/logout', LogoutController::class)
    ->middleware(['tenant', 'branch', 'auth'])
    ->name('logout');

Route::get('/admin/branches/{branch}', BranchShowController::class)
    ->middleware(['tenant', 'branch', 'auth'])
    ->name('admin.branches.show');

Route::get('/admin', AdminDashboardController::class)
    ->middleware(['tenant', 'branch', 'auth'])
    ->name('admin.dashboard');

Route::post('/admin/branch', AdminBranchSwitchController::class)
    ->middleware(['tenant', 'branch', 'auth'])
    ->name('admin.branch.switch');

Route::post('/admin/locale', AdminLocaleSwitchController::class)
    ->middleware(['tenant', 'branch', 'auth'])
    ->name('admin.locale.switch');

Route::middleware(['tenant', 'branch', 'auth'])->prefix('/admin/menu')->name('admin.menu.')->group(function (): void {
    Route::get('/', MenuIndexController::class)
        ->middleware('can:menu.items.manage')
        ->name('index');

    Route::get('/categories/create', [MenuCategoryController::class, 'create'])
        ->middleware('can:menu.categories.manage')
        ->name('categories.create');
    Route::post('/categories', [MenuCategoryController::class, 'store'])
        ->middleware('can:menu.categories.manage')
        ->name('categories.store');
    Route::get('/categories/{category}/edit', [MenuCategoryController::class, 'edit'])
        ->middleware('can:menu.categories.manage')
        ->name('categories.edit');
    Route::put('/categories/{category}', [MenuCategoryController::class, 'update'])
        ->middleware('can:menu.categories.manage')
        ->name('categories.update');
    Route::delete('/categories/{category}', [MenuCategoryController::class, 'destroy'])
        ->middleware('can:menu.categories.manage')
        ->name('categories.destroy');

    Route::get('/items/create', [MenuItemController::class, 'create'])
        ->middleware('can:menu.items.manage')
        ->name('items.create');
    Route::post('/items', [MenuItemController::class, 'store'])
        ->middleware('can:menu.items.manage')
        ->name('items.store');
    Route::get('/items/{item}/edit', [MenuItemController::class, 'edit'])
        ->middleware('can:menu.items.manage')
        ->name('items.edit');
    Route::put('/items/{item}', [MenuItemController::class, 'update'])
        ->middleware('can:menu.items.manage')
        ->name('items.update');
    Route::delete('/items/{item}', [MenuItemController::class, 'destroy'])
        ->middleware('can:menu.items.manage')
        ->name('items.destroy');
});
