<?php
// routes/test.php - Archivo temporal para debug
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->get('/test-roles', function () {
    $user = auth()->user();
    
    return response()->json([
        'user_id' => $user->id,
        'email' => $user->email,
        'name' => $user->name,
        'roles' => $user->roles->pluck('name'),
        'has_control' => $user->hasRole('control'),
        'has_comercial' => $user->hasRole('comercial'),
        'has_any_role' => $user->hasAnyRole(['admin','coordinador','control','comercial']),
        'guard' => config('auth.defaults.guard'),
        'session_driver' => config('session.driver'),
    ]);
});
