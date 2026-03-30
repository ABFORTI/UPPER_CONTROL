<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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
            'feature' => \App\Http\Middleware\CheckFeature::class,
        ]);
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/integraciones/etiquetas/*')) {
                Log::warning('TEMP DEBUG etiquetas: autenticacion fallida', [
                    'route' => $request->path(),
                    'method' => $request->method(),
                    'ip' => $request->ip(),
                    'auth_header_present' => $request->headers->has('Authorization'),
                    'message' => $e->getMessage(),
                ]);

                return response()->json([
                    'ok' => false,
                    'mensaje' => 'Autenticacion requerida o token invalido.',
                    'detalle' => 'Envia Authorization Bearer con token Sanctum vigente para un usuario con rol integracion_api.',
                ], 401);
            }

            return null;
        });

        $exceptions->render(function (PostTooLargeException $e, Request $request) {
            $message = 'El archivo o formulario supera el tamano maximo permitido por el servidor. Intenta con un archivo mas pequeno o aumenta post_max_size/upload_max_filesize.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 413);
            }

            return back()->withErrors(['archivo' => $message]);
        });

        // Deshabilitar renderizado visual de errores en producción
        // para evitar problemas con highlight_file() deshabilitado
        if (!config('app.debug')) {
            $exceptions->dontReport([]);
        }
    })->create();
