<?php

use App\Http\Middleware\EnsureContentLength;
use App\Http\Middleware\EnsurePlatformApiRequest;
use App\Http\Middleware\EnsurePlatformPortalUser;
use App\Http\Middleware\EnsureTenantPortalUser;
use App\Http\Middleware\ResolveTenantFromMaster;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\ValidateSignature;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            EnsureContentLength::class,
        ]);

        $middleware->alias([
            'tenant' => ResolveTenantFromMaster::class,
            'platform.api' => EnsurePlatformApiRequest::class,
            'portal.tenant' => EnsureTenantPortalUser::class,
            'portal.platform' => EnsurePlatformPortalUser::class,
            'signed' => ValidateSignature::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Mobile file downloads send Accept: */*. Without this, a 401 or 404
        // becomes an HTML redirect and the phone reports a connection error.
        $exceptions->handler->shouldRenderJsonWhen(
            static fn ($request, $exception): bool => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
