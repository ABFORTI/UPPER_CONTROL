<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$orden = App\Models\Orden::find(49);

echo "Orden 49:\n";
echo "  Estatus: {$orden->estatus}\n";
echo "  Calidad: {$orden->calidad_resultado}\n";
echo "  Cliente Autorizada At: " . ($orden->cliente_autorizada_at ?? 'NULL') . "\n";
