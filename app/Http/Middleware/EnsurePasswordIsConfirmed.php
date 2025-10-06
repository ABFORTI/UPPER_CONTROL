<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsurePasswordIsConfirmed
{
    public function handle(Request $request, Closure $next, $redirect = 'password.confirm')
    {
        // Implementación mínima: dejar pasar siempre (no se usa en esta app por ahora)
        return $next($request);
    }
}
