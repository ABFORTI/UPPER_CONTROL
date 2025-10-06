<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RestrictCalidadOnly
{
    /**
     * Maneja una petición entrante.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        // Aplica solo si el usuario tiene UNICAMENTE el rol 'calidad'
        if ($user->hasRole('calidad') && $user->roles()->count() === 1) {
            $route = $request->route();
            $name  = $route?->getName();

            // Lista blanca de rutas permitidas para "solo-calidad"
            $allowed = [
                // Calidad
                'calidad.index','calidad.show','calidad.validar','calidad.rechazar',
                // Ver OT puntual (mostrar detalles)
                'ordenes.show',
                // Notificaciones
                'notificaciones.index','notificaciones.read_all',
                // Perfil / logout
                'profile.edit','profile.update','profile.destroy','logout',
                // Impersonación (por si estaba impersonado y debe salir)
                'admin.impersonate.leave',
                // Página inicial redirigida
                'home',
            ];

            if ($name === null) {
                // Si no tiene nombre dejamos pasar (assets, etc.)
                return $next($request);
            }

            if (!in_array($name, $allowed, true)) {
                // Redirigir siempre al panel de calidad (idempotente)
                return redirect()->route('calidad.index');
            }
        }

        return $next($request);
    }
}
