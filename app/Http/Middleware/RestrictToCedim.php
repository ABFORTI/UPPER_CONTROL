<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictToCedim
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Admin siempre tiene acceso
        if ($user && $user->hasRole('admin')) {
            return $next($request);
        }

        // Verificar que el usuario pertenezca al centro CEDIM (ID: 1)
        if (!$user || $user->centro_trabajo_id !== 1) {
            abort(403, 'No tienes acceso a esta sección. Esta funcionalidad está disponible solo para el centro CEDIM.');
        }

        return $next($request);
    }
}
