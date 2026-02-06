<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\OTServicio;
use App\Models\OTServicioAvance;
use Illuminate\Support\Facades\DB;

echo "=== Recalculando subtotales de servicios con avances ===\n\n";

// Obtener todos los servicios que tienen avances
$serviciosConAvances = OTServicio::whereHas('avances')->with('avances')->get();

echo "Servicios encontrados: " . $serviciosConAvances->count() . "\n\n";

foreach ($serviciosConAvances as $servicio) {
    echo "Servicio ID: {$servicio->id}\n";
    echo "Subtotal actual: {$servicio->subtotal}\n";
    
    // Calcular subtotal basado en avances
    $subtotalCalculado = 0;
    foreach ($servicio->avances as $avance) {
        $cantidad = (int)$avance->cantidad_registrada;
        $precio = (float)$avance->precio_unitario_aplicado;
        $subtotal = round($cantidad * $precio, 2);
        $subtotalCalculado += $subtotal;
        
        echo "  - Avance #{$avance->id}: {$cantidad} × \${$precio} = \${$subtotal}\n";
    }
    
    echo "Subtotal calculado: \${$subtotalCalculado}\n";
    
    // Actualizar directamente con query builder para evitar el evento boot
    DB::table('ot_servicios')
        ->where('id', $servicio->id)
        ->update(['subtotal' => $subtotalCalculado]);
    
    echo "✅ Actualizado\n\n";
    
    // Actualizar totales de la OT
    $orden = $servicio->ot;
    if ($orden) {
        $subtotalOT = DB::table('ot_servicios')
            ->where('ot_id', $orden->id)
            ->sum('subtotal');
        
        $ivaOT = round($subtotalOT * 0.16, 2);
        $totalOT = $subtotalOT + $ivaOT;
        
        DB::table('ordenes_trabajo')
            ->where('id', $orden->id)
            ->update([
                'subtotal' => $subtotalOT,
                'iva' => $ivaOT,
                'total' => $totalOT,
                'total_real' => $subtotalOT,
            ]);
        
        echo "  OT #{$orden->id} actualizada: Subtotal={$subtotalOT}, IVA={$ivaOT}, Total={$totalOT}\n\n";
    }
}

echo "\n=== Recálculo completado ===\n";
