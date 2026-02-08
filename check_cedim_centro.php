<?php
require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Buscar el centro CEDIM
$cedim = \App\Models\CentroTrabajo::where('nombre', 'like', '%CEDIM%')->first();

if ($cedim) {
    echo "Centro CEDIM encontrado:\n";
    echo "ID: {$cedim->id}\n";
    echo "Nombre: {$cedim->nombre}\n";
} else {
    echo "No se encontró centro con nombre CEDIM\n";
    echo "\nTodos los centros:\n";
    foreach (\App\Models\CentroTrabajo::all() as $centro) {
        echo "  - ID {$centro->id}: {$centro->nombre}\n";
    }
}
