<?php

use App\Http\Middleware\ResolveTenantFromMaster;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'tenant' => ResolveTenantFromMaster::class,
            'platform.api' => \App\Http\Middleware\EnsurePlatformApiRequest::class,
            'portal.tenant' => \App\Http\Middleware\EnsureTenantPortalUser::class,
            'portal.platform' => \App\Http\Middleware\EnsurePlatformPortalUser::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
