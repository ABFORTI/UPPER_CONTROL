<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== ÚLTIMOS 5 AVANCES REGISTRADOS ===\n\n";

$avances = DB::table('ot_servicio_avances')
    ->orderBy('id', 'desc')
    ->limit(5)
    ->get();

if ($avances->isEmpty()) {
    echo "No hay avances registrados\n";
} else {
    foreach ($avances as $a) {
        echo "ID: {$a->id}\n";
        echo "  Servicio OT: {$a->ot_servicio_id}\n";
        echo "  Tarifa: {$a->tarifa}\n";
        echo "  Precio Aplicado: " . ($a->precio_unitario_aplicado !== null ? '$' . number_format($a->precio_unitario_aplicado, 2) : 'NULL') . "\n";
        echo "  Cantidad: {$a->cantidad_registrada}\n";
        echo "  Comentario: " . ($a->comentario ?: '(sin comentario)') . "\n";
        echo "  Creado: {$a->created_at}\n";
        echo "  Usuario: {$a->created_by}\n";
        echo "---\n";
    }
}
