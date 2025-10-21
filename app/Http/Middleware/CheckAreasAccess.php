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
