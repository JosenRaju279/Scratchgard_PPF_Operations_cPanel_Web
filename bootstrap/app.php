<?php

use App\Http\Middleware\EnsureInstalled;
use App\Http\Middleware\PermissionMiddleware;
use App\Http\Middleware\ApiTokenMiddleware;
use App\Http\Middleware\PartnerApiMiddleware;
use App\Http\Middleware\EnsureSuperAdmin;
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
            'installed' => EnsureInstalled::class,
            'permission' => PermissionMiddleware::class,
            'api.token' => ApiTokenMiddleware::class,
            'partner.api' => PartnerApiMiddleware::class,
            'superadmin' => EnsureSuperAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
