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

        $featureKeys = array_filter(array_map('trim', preg_split('/[|,]+/', $featureKey)));
        foreach ($featureKeys as $key) {
            if ($centro->hasFeature($key)) {
                return $next($request);
            }

            if ($user && method_exists($user, 'hasPermissionTo') && $user->hasPermissionTo($key)) {
                return $next($request);
            }
        }

        abort(403, 'No tienes acceso a esta funcionalidad.');
    }
}
