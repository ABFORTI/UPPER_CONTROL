<?php
// app/Http/Middleware/ImpersonationFlags.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ImpersonationFlags
{
    public function handle(Request $request, Closure $next)
    {
        if (session()->has('impersonated_by')) {
            // Bandera que puedes leer en cualquier parte
            app()->instance('impersonating', true);
        }
        return $next($request);
    }
}
