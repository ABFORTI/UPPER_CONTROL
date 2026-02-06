<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\OTServicioAvance;
use Illuminate\Support\Facades\DB;

echo "=== Limpiando avances duplicados ===\n\n";

// Agrupar avances por servicio, tarifa, precio y fecha (mismo minuto)
$avances = OTServicioAvance::with('otServicio')
    ->orderBy('ot_servicio_id')
    ->orderBy('created_at')
    ->get();

$duplicados = [];
$mantener = [];

foreach ($avances as $avance) {
    $key = sprintf(
        '%d_%s_%s_%s_%d',
        $avance->ot_servicio_id,
        $avance->tarifa,
        $avance->precio_unitario_aplicado,
        date('Y-m-d H:i', strtotime($avance->created_at)), // Mismo minuto
        $avance->cantidad_registrada
    );
    
    if (isset($mantener[$key])) {
        // Es un duplicado - marcar para eliminar
        $duplicados[] = $avance->id;
        echo "🔴 DUPLICADO encontrado:\n";
        echo "  ID: {$avance->id}\n";
        echo "  Servicio: {$avance->ot_servicio_id}\n";
        echo "  Tarifa: {$avance->tarifa}\n";
        echo "  Precio: {$avance->precio_unitario_aplicado}\n";
        echo "  Cantidad: {$avance->cantidad_registrada}\n";
        echo "  Fecha: {$avance->created_at}\n";
        echo "  (Original ID: {$mantener[$key]})\n\n";
    } else {
        // Es el primer avance con estas características - mantener
        $mantener[$key] = $avance->id;
    }
}

echo "\nTotal de duplicados encontrados: " . count($duplicados) . "\n";

if (count($duplicados) > 0) {
    echo "\n¿Deseas eliminar estos " . count($duplicados) . " avances duplicados? (escribe 'SI' para confirmar): ";
    $handle = fopen("php://stdin", "r");
    $line = trim(fgets($handle));
    
    if (strtoupper($line) === 'SI') {
        DB::table('ot_servicio_avances')->whereIn('id', $duplicados)->delete();
        echo "\n✅ Avances duplicados eliminados\n";
        
        // Recalcular subtotales
        echo "\nRecalculando subtotales...\n";
        require __DIR__.'/recalcular_subtotales_multiservicio.php';
    } else {
        echo "\n❌ Operación cancelada\n";
    }
} else {
    echo "\n✅ No se encontraron duplicados\n";
}
