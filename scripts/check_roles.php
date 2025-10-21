<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Spatie\Permission\Models\Role;

echo "Roles en la base de datos:\n";
echo str_repeat("=", 60) . "\n";

foreach (Role::all() as $r) {
    $length = strlen($r->name);
    $hex = bin2hex($r->name);
    echo sprintf("ID: %-3d | Name: [%s] (len:%d) | Guard: %s\n", 
        $r->id, $r->name, $length, $r->guard_name);
    
    // Mostrar hex para detectar caracteres ocultos
    if ($r->name === 'control' || $r->name === 'comercial') {
        echo "       Hex: $hex\n";
    }
}

echo "\n";
