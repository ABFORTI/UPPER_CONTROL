<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckFeature
{
    /**
     * Middleware: valida si el centro de trabajo del usuario tiene habilitada la feature.
     *
     * Uso:
     *   ->middleware('feature:ver_cotizacion')
     */
    public function handle(Request $request, Closure $next, string $featureKey): Response
    {
        $user = $request->user();

        // Admin siempre tiene acceso (evita bloquear tareas de soporte/configuración).
        if ($user && method_exists($user, 'hasRole') && $user->hasRole('admin')) {
            return $next($request);
        }

        $centro = $user?->centro;
        if (!$centro) {
            abort(403, 'No tienes un centro de trabajo asignado.');
        }

        if (!$centro->hasFeature($featureKey)) {
            abort(403, 'No tienes acceso a esta funcionalidad.');
        }

        return $next($request);
    }
}
