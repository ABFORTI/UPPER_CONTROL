<?php
// Debug script — borrar después de diagnosticar
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== DIAGNÓSTICO DE BLOQUEO 403 ===\n\n";

// 1. Verificar roles existentes
echo "ROLES EN BD:\n";
$roles = DB::table('roles')->get(['id','name','guard_name']);
foreach ($roles as $r) {
    echo "  [{$r->id}] {$r->name} (guard: {$r->guard_name})\n";
}

// 2. Buscar usuario admin
echo "\nUSUARIOS CON ROL ADMIN:\n";
$admins = App\Models\User::whereHas('roles', function($q){ $q->where('name','admin'); })->get(['id','name','email']);
foreach ($admins as $u) {
    $roleNames = $u->getRoleNames()->implode(', ');
    echo "  [{$u->id}] {$u->name} | {$u->email} | Roles: {$roleNames}\n";
    echo "  hasRole('admin'): " . ($u->hasRole('admin') ? 'SI' : 'NO') . "\n";
    echo "  hasAnyRole(['admin','facturacion']): " . ($u->hasAnyRole(['admin','facturacion']) ? 'SI' : 'NO') . "\n";
}

// 3. Verificar usuarios cliente (los que se pueden bloquear)
echo "\nUSUARIOS CLIENTE_SUPERVISOR / CLIENTE_GERENTE:\n";
$clientes = App\Models\User::whereHas('roles', function($q){
    $q->whereIn('name', ['Cliente_Supervisor', 'Cliente_Gerente']);
})->get(['id','name','email']);
foreach ($clientes as $u) {
    $roleNames = $u->getRoleNames()->implode(', ');
    echo "  [{$u->id}] {$u->name} | Roles: {$roleNames}\n";
}

// 4. Verificar ruta
echo "\nRUTAS BLOQUEO:\n";
$router = app('router');
$routes = $router->getRoutes();
foreach ($routes as $route) {
    if (str_contains($route->uri(), 'usuarios-bloqueo')) {
        echo "  [{$route->methods()[0]}] /{$route->uri()}\n";
        echo "    Name: {$route->getName()}\n";
        $mw = implode(', ', $route->gatherMiddleware());
        echo "    Middleware: {$mw}\n";
    }
}

echo "\n=== FIN ===\n";
