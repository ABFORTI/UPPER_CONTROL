<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "\n=== VERIFICANDO TRIGGERS EN ot_servicio_avances ===\n\n";

$triggers = DB::select("
    SELECT 
        TRIGGER_NAME,
        EVENT_MANIPULATION,
        EVENT_OBJECT_TABLE,
        ACTION_TIMING,
        ACTION_STATEMENT
    FROM INFORMATION_SCHEMA.TRIGGERS
    WHERE EVENT_OBJECT_SCHEMA = DATABASE()
    AND EVENT_OBJECT_TABLE = 'ot_servicio_avances'
");

if (empty($triggers)) {
    echo "✅ No hay triggers en la tabla ot_servicio_avances\n\n";
} else {
    echo "⚠️ Se encontraron triggers:\n\n";
    foreach ($triggers as $trigger) {
        echo "Nombre: {$trigger->TRIGGER_NAME}\n";
        echo "Evento: {$trigger->EVENT_MANIPULATION}\n";
        echo "Timing: {$trigger->ACTION_TIMING}\n";
        echo "Statement: {$trigger->ACTION_STATEMENT}\n";
        echo str_repeat('-', 80) . "\n";
    }
}

echo "\n=== VERIFICANDO ÚLTIMA INSERCIÓN ===\n\n";

$ultimosAvances = DB::table('ot_servicio_avances')
    ->where('ot_servicio_id', 50)
    ->orderBy('created_at', 'desc')
    ->take(10)
    ->get(['id', 'tarifa', 'cantidad_registrada', 'precio_unitario_aplicado', 'created_at']);

foreach ($ultimosAvances as $avance) {
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
