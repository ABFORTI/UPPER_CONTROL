<?php
// app/Http/Controllers/Admin/ImpersonateController.php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class ImpersonateController extends Controller
{
    public function start(User $user)
    {
        // No te impersones a ti mismo
        if (Auth::id() === $user->id) {
            return back()->withErrors(['impersonate' => 'Ya estás en esa sesión.']);
        }

        // (Opcional) bloquear impersonar a otros admins
        if ($user->hasRole('admin')) {
            return back()->withErrors(['impersonate' => 'No se permite impersonar a administradores.']);
        }

        // Guarda quién es el admin original
        session(['impersonated_by' => Auth::id()]);

        // Cambia sesión al usuario destino
        Auth::login($user);

        return redirect()->route('dashboard')->with('ok', "Estás impersonando a {$user->name}");
    }

    public function leave()
    {
        $originalId = session('impersonated_by');

        // Si no vienes de impersonación, no permitas
        if (!$originalId) {
            return redirect()->route('dashboard')->withErrors(['impersonate' => 'No hay impersonación activa.']);
        }

        // Vuelve al admin original
        session()->forget('impersonated_by');
        \Illuminate\Support\Facades\Auth::logout();
        \Illuminate\Support\Facades\Auth::loginUsingId($originalId);

        return redirect()->route('admin.users.index')->with('ok','Has salido de la impersonación.');
    }
}

