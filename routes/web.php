<?php

declare(strict_types=1);

use App\Http\Controllers\AdminBranchSwitchController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminLocaleSwitchController;
use App\Modules\Identity\Http\Controllers\LoginController;
use App\Modules\Identity\Http\Controllers\LogoutController;
use App\Modules\Menu\Http\Controllers\MenuCategoryController;
use App\Modules\Menu\Http\Controllers\MenuCategoryOptionController;
use App\Modules\Menu\Http\Controllers\MenuIndexController;
use App\Modules\Menu\Http\Controllers\MenuItemController;
use App\Modules\Tables\Http\Controllers\HallController;
use App\Modules\Tables\Http\Controllers\TableController;
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
    Route::get('/categories/options/parents', [MenuCategoryOptionController::class, 'parents'])
        ->middleware('can:menu.categories.manage')
        ->name('category-options.parents');
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
    Route::post('/categories/{category}/restore', [MenuCategoryController::class, 'restore'])
        ->middleware(['can:menu.categories.manage', 'superadmin'])
        ->name('categories.restore');
    Route::delete('/categories/{category}/force-delete', [MenuCategoryController::class, 'forceDelete'])
        ->middleware(['can:menu.categories.manage', 'superadmin'])
        ->name('categories.force-delete');

    Route::get('/items/create', [MenuItemController::class, 'create'])
        ->middleware('can:menu.items.manage')
        ->name('items.create');
    Route::get('/items/options/categories', [MenuCategoryOptionController::class, 'itemCategories'])
        ->middleware('can:menu.items.manage')
        ->name('category-options.item-categories');
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
    Route::post('/items/{item}/restore', [MenuItemController::class, 'restore'])
        ->middleware(['can:menu.items.manage', 'superadmin'])
        ->name('items.restore');
    Route::delete('/items/{item}/force-delete', [MenuItemController::class, 'forceDelete'])
        ->middleware(['can:menu.items.manage', 'superadmin'])
        ->name('items.force-delete');
});

Route::middleware(['tenant', 'branch', 'auth'])->prefix('/admin/tables/halls')->name('admin.tables.halls.')->group(function (): void {
    Route::get('/', [HallController::class, 'index'])
        ->middleware('can:tables.halls.manage')
        ->name('index');
    Route::get('/create', [HallController::class, 'create'])
        ->middleware('can:tables.halls.manage')
        ->name('create');
    Route::post('/', [HallController::class, 'store'])
        ->middleware('can:tables.halls.manage')
        ->name('store');
    Route::get('/{hall}/edit', [HallController::class, 'edit'])
        ->middleware('can:tables.halls.manage')
        ->name('edit');
    Route::put('/{hall}', [HallController::class, 'update'])
        ->middleware('can:tables.halls.manage')
        ->name('update');
    Route::delete('/{hall}', [HallController::class, 'destroy'])
        ->middleware('can:tables.halls.manage')
        ->name('destroy');
    Route::post('/{hall}/restore', [HallController::class, 'restore'])
        ->middleware(['can:tables.halls.manage', 'superadmin'])
        ->name('restore');
    Route::delete('/{hall}/force-delete', [HallController::class, 'forceDelete'])
        ->middleware(['can:tables.halls.manage', 'superadmin'])
        ->name('force-delete');
});

Route::middleware(['tenant', 'branch', 'auth'])->prefix('/admin/tables/halls/{hall}/tables')->name('admin.tables.tables.')->group(function (): void {
    Route::get('/', [TableController::class, 'index'])
        ->middleware('can:tables.tables.manage')
        ->name('index');
    Route::get('/create', [TableController::class, 'create'])
        ->middleware('can:tables.tables.manage')
        ->name('create');
    Route::post('/', [TableController::class, 'store'])
        ->middleware('can:tables.tables.manage')
        ->name('store');
    Route::get('/{table}/edit', [TableController::class, 'edit'])
        ->middleware('can:tables.tables.manage')
        ->name('edit');
    Route::put('/{table}', [TableController::class, 'update'])
        ->middleware('can:tables.tables.manage')
        ->name('update');
    Route::delete('/{table}', [TableController::class, 'destroy'])
        ->middleware('can:tables.tables.manage')
        ->name('destroy');
    Route::post('/{table}/restore', [TableController::class, 'restore'])
        ->middleware(['can:tables.tables.manage', 'superadmin'])
        ->name('restore');
    Route::delete('/{table}/force-delete', [TableController::class, 'forceDelete'])
        ->middleware(['can:tables.tables.manage', 'superadmin'])
        ->name('force-delete');
});
