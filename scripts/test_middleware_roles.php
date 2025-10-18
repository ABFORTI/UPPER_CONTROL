<?php
// scripts/test_middleware_roles.php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

$users = [
    'control@gmail.com',
    'comercial@gmail.com',
];

echo "Probando verificación de roles para middleware 'admin|coordinador|control|comercial':\n\n";

foreach ($users as $email) {
    $user = User::where('email', $email)->first();
    if (!$user) {
        echo "❌ Usuario $email no encontrado\n";
        continue;
    }
    
    echo "Usuario: {$user->email} (ID: {$user->id})\n";
    echo "  - Roles: " . implode(', ', $user->roles->pluck('name')->toArray()) . "\n";
    
    // Probar hasRole para cada rol del middleware
    $allowedRoles = ['admin', 'coordinador', 'control', 'comercial'];
    $hasAccess = false;
    foreach ($allowedRoles as $role) {
        if ($user->hasRole($role)) {
            echo "  ✅ hasRole('$role'): TRUE\n";
            $hasAccess = true;
            break;
        }
    }
    
    // Probar hasAnyRole
    $hasAnyRole = $user->hasAnyRole($allowedRoles);
    echo "  - hasAnyRole(['admin','coordinador','control','comercial']): " . ($hasAnyRole ? 'TRUE ✅' : 'FALSE ❌') . "\n";
    
    echo "  RESULTADO: " . ($hasAccess || $hasAnyRole ? '✅ DEBERÍA TENER ACCESO' : '❌ NO TIENE ACCESO') . "\n\n";
}
