<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $user = $request->user();
        if (!$user->activo) {
            Auth::guard('web')->logout();
            return back()->withErrors(['email'=>'Tu usuario está desactivado.']);
        }

        $request->session()->regenerate();

        // Marcar modo splash para mostrar pantalla de inicio de sesión
        cookie()->queue(cookie('splash_mode', 'login', 1, null, null, false, false)); // 1 minuto
        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        // Marcar modo splash para mostrar pantalla de cierre de sesión
        cookie()->queue(cookie('splash_mode', 'logout', 1, null, null, false, false)); // 1 minuto
        return redirect('/');
    }
}
