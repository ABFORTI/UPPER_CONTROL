<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    /**
     * Redirige a dashboard.
     * Acepta cualquier método HTTP para evitar errores 405.
     */
    public function index(Request $request): RedirectResponse
    {
        // Si no está autenticado, redirigir a login
        if (!auth()->check()) {
            return redirect()->route('login');
        }
        
        // Si está autenticado, redirigir a dashboard
        return redirect()->route('dashboard');
    }
}
