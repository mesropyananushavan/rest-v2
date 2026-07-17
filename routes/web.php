<?php

use App\Modules\Tenancy\Http\Controllers\BranchShowController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/admin/branches/{branch}', BranchShowController::class)
    ->middleware('auth')
    ->name('admin.branches.show');
