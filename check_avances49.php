<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "\n=== AVANCES DEL SERVICIO 49 ===\n\n";

$avances = DB::table('ot_servicio_avances')
    ->where('ot_servicio_id', 49)
    ->orderBy('created_at')
    ->get(['id', 'tarifa', 'cantidad_registrada', 'precio_unitario_aplicado', 'created_at']);

echo "Total avances: " . $avances->count() . "\n\n";

foreach ($avances as $avance) {
    echo sprintf(
        "ID: %d | Tarifa: %s | Cantidad: %d | Precio: $%.2f | Fecha: %s\n",
        $avance->id,
        $avance->tarifa,
        $avance->cantidad_registrada,
        $avance->precio_unitario_aplicado,
        $avance->created_at
    );
}

echo "\n";
