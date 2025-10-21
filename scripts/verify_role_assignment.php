<?php
// scripts/verify_role_assignment.php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Verificando asignación de roles 'control' y 'comercial'...\n\n";

$assignments = DB::table('model_has_roles')
    ->where('model_type', 'App\\Models\\User')
    ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
    ->join('users', function($join) {
        $join->on('model_has_roles.model_id', '=', 'users.id')
             ->where('model_has_roles.model_type', '=', 'App\\Models\\User');
    })
    ->whereIn('roles.name', ['control', 'comercial'])
    ->select('users.id', 'users.email', 'users.name', 'roles.name as role_name')
    ->get();

if ($assignments->isEmpty()) {
    echo "❌ No hay usuarios con roles 'control' o 'comercial' asignados.\n";
} else {
    echo "✅ Usuarios con roles control/comercial:\n";
    foreach ($assignments as $a) {
        echo "  - [{$a->id}] {$a->email} ({$a->name}) -> Rol: {$a->role_name}\n";
    }
}

echo "\n";
