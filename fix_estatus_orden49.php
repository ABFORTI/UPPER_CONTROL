<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$orden = App\Models\Orden::find(49);

echo "Orden 49 - Estado actual:\n";
echo "  Estatus: {$orden->estatus}\n";
echo "  Calidad: {$orden->calidad_resultado}\n";

if ($orden->estatus === 'completada') {
    $orden->estatus = 'autorizada_cliente';
    $orden->save();
    echo "\n✅ Estatus actualizado a 'autorizada_cliente'\n";
} else {
    echo "\n✅ El estatus ya es correcto: {$orden->estatus}\n";
}
