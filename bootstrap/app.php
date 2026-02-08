<?php

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
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
            \App\Http\Middleware\ImpersonationFlags::class,
        ]);
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'check.servicios' => \App\Http\Middleware\CheckServiciosAccess::class,
            'check.areas' => \App\Http\Middleware\CheckAreasAccess::class,
            'cedim.only' => \App\Http\Middleware\RestrictToCedim::class,
        ]);
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Deshabilitar renderizado visual de errores en producción
        // para evitar problemas con highlight_file() deshabilitado
        if (!config('app.debug')) {
            $exceptions->dontReport([]);
        }
    })->create();
