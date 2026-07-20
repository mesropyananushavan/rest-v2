<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

it('renders the translated admin 403 page', function (): void {
    Route::middleware('web')->get('/_test/admin-forbidden', fn () => abort(403));

    $this->get('/_test/admin-forbidden')
        ->assertForbidden()
        ->assertSee(__('admin.errors.403.title'), false)
        ->assertSee(__('admin.errors.403.message'), false);
});

it('renders the translated admin 404 page', function (): void {
    $this->get('/_test/admin-missing')
        ->assertNotFound()
        ->assertSee(__('admin.errors.404.title'), false)
        ->assertSee(__('admin.errors.404.message'), false);
});

it('renders the translated admin 500 page', function (): void {
    config(['app.debug' => false]);

    Route::middleware('web')->get('/_test/admin-error', function (): never {
        throw new RuntimeException('Admin error page test exception.');
    });

    $this->get('/_test/admin-error')
        ->assertStatus(500)
        ->assertSee(__('admin.errors.500.title'), false)
        ->assertSee(__('admin.errors.500.message'), false);
});
