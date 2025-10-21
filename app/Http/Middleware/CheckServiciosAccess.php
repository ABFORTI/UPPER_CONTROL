<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class CheckServiciosAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        
        if (!$user) {
            Log::error('CheckServiciosAccess: Usuario no autenticado');
            abort(403, 'No autenticado');
        }
        
        // Debug: log información del usuario
        Log::info('CheckServiciosAccess', [
            'user_id' => $user->id,
            'email' => $user->email,
            'roles' => $user->roles->pluck('name')->toArray(),
            'has_control' => $user->hasRole('control'),
            'has_comercial' => $user->hasRole('comercial'),
            'has_any_role' => $user->hasAnyRole(['admin', 'coordinador', 'control', 'comercial']),
            'can_servicios_list' => $user->can('servicios.list'),
        ]);
        
        // Permitir acceso si tiene alguno de estos roles O el permiso específico
        if ($user->hasAnyRole(['admin', 'coordinador', 'control', 'comercial']) || 
            $user->can('servicios.list')) {
            Log::info('CheckServiciosAccess: Acceso permitido');
            return $next($request);
        }
        
        Log::error('CheckServiciosAccess: Acceso denegado', [
            'user_id' => $user->id,
            'email' => $user->email,
        ]);
        abort(403, 'No tienes permisos para acceder a servicios.');
    }
}
