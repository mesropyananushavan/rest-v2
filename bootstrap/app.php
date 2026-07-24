<?php

declare(strict_types=1);

use App\Console\Commands\MenuContextSmokeCommand;
use App\Console\Commands\MenuLoadTestDataCommand;
use App\Console\Commands\MenuSeedLoadCommand;
use App\Http\Middleware\AttachLogContext;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\EnsureSuperAdminForDeletes;
use App\Modules\Menu\Domain\MenuDomainException;
use App\Modules\Tables\Domain\TablesDomainException;
use App\Modules\Tenancy\Http\Middleware\ResolveBranch;
use App\Modules\Tenancy\Http\Middleware\ResolveTenant;
use App\Support\Api\ApiErrorRenderer;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        MenuContextSmokeCommand::class,
        MenuLoadTestDataCommand::class,
        MenuSeedLoadCommand::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'tenant' => ResolveTenant::class,
            'branch' => ResolveBranch::class,
            'superadmin' => EnsureSuperAdmin::class,
            'superadmin.delete' => EnsureSuperAdminForDeletes::class,
        ]);

        $middleware->appendToGroup('web', [
            AttachLogContext::class,
            ResolveTenant::class,
            ResolveBranch::class,
        ]);

        $middleware->redirectUsersTo('/admin');

        $middleware->prependToPriorityList(AuthenticatesRequests::class, AttachLogContext::class);
        $middleware->prependToPriorityList(AuthenticatesRequests::class, ResolveBranch::class);
        $middleware->prependToPriorityList(ResolveBranch::class, ResolveTenant::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(function (MenuDomainException $exception, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return ApiErrorRenderer::menuDomain($exception, $request);
            }

            return back()
                ->withErrors(['menu' => __($exception->errorCode())])
                ->withInput();
        });

        $exceptions->render(function (TablesDomainException $exception, Request $request) {
            return back()
                ->withErrors(['tables' => __($exception->errorCode())])
                ->withInput();
        });

        ApiErrorRenderer::register($exceptions);
    })->create();
