<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class CheckAreasAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        
        if (!$user) {
            Log::error('CheckAreasAccess: Usuario no autenticado');
            abort(403, 'No autenticado');
        }
        
        // Debug: log información del usuario
        Log::info('CheckAreasAccess', [
            'user_id' => $user->id,
            'email' => $user->email,
            'roles' => $user->roles->pluck('name')->toArray(),
            'has_control' => $user->hasRole('control'),
            'has_comercial' => $user->hasRole('comercial'),
            'has_any_role' => $user->hasAnyRole(['admin', 'coordinador', 'control', 'comercial']),
            'can_areas_list' => $user->can('areas.list'),
        ]);
        
        // Rol GERENTE_UPPER: sólo lectura (permitir únicamente métodos seguros)
        if ($user->hasRole('gerente_upper')) {
            if (in_array(strtoupper($request->method()), ['GET','HEAD','OPTIONS'], true)) {
                Log::info('CheckAreasAccess: Acceso gerente_upper (solo lectura)');
                return $next($request);
            }
            Log::warning('CheckAreasAccess: gerente_upper bloqueado en método no seguro', ['method' => $request->method(), 'path' => $request->path()]);
            abort(403, 'Solo lectura: no puedes modificar esta sección.');
        }

        // Permitir acceso si tiene alguno de estos roles O el permiso específico
        if ($user->hasAnyRole(['admin', 'coordinador', 'control', 'comercial']) || 
            $user->can('areas.list')) {
            Log::info('CheckAreasAccess: Acceso permitido');
            return $next($request);
        }
        
        Log::error('CheckAreasAccess: Acceso denegado', [
            'user_id' => $user->id,
            'email' => $user->email,
        ]);
        abort(403, 'No tienes permisos para acceder a áreas.');
    }
}
