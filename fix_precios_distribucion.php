<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

DB::transaction(function() {
    echo "Corrigiendo precios del servicio Distribución (OT 23)...\n";
    echo "========================================================\n\n";
    
    // Obtener el servicio Distribución (id 24)
    $servicio = DB::table('ot_servicios')->where('id', 24)->first();
    
    if (!$servicio) {
        echo "❌ Servicio no encontrado\n";
        return;
    }
    
    $precioUnitarioServicio = (float)$servicio->precio_unitario;
    echo "Precio unitario del servicio: $" . number_format($precioUnitarioServicio, 2) . "\n\n";
    
    // Actualizar todos los items del servicio con el mismo precio
    $items = DB::table('ot_servicio_items')->where('ot_servicio_id', 24)->get();
    $subtotalTotal = 0;
    
    foreach ($items as $item) {
        $nuevoSubtotal = $item->planeado * $precioUnitarioServicio;
        
        DB::table('ot_servicio_items')->where('id', $item->id)->update([
            'precio_unitario' => $precioUnitarioServicio,
            'subtotal' => $nuevoSubtotal
        ]);
        
        $subtotalTotal += $nuevoSubtotal;
        
        echo "✓ Item {$item->id} ({$item->descripcion_item}): {$item->planeado} x \${$precioUnitarioServicio} = \$" . number_format($nuevoSubtotal, 2) . "\n";
    }
    
    // Actualizar subtotal del servicio
    DB::table('ot_servicios')->where('id', 24)->update([
        'subtotal' => $subtotalTotal
    ]);
    
    echo "\n✓ Subtotal del servicio actualizado: $" . number_format($subtotalTotal, 2) . "\n";
    
    // Recalcular totales de la OT
    $subtotalOT = DB::table('ot_servicios')->where('ot_id', 23)->sum('subtotal');
    $ivaOT = $subtotalOT * 0.16;
    $totalOT = $subtotalOT + $ivaOT;
    
    DB::table('ordenes_trabajo')->where('id', 23)->update([
        'subtotal' => $subtotalOT,
        'iva' => $ivaOT,
        'total' => $totalOT,
        'total_real' => $subtotalOT
    ]);
    
    echo "\n✅ Totales de la OT 23 actualizados:\n";
    echo "   Subtotal: $" . number_format($subtotalOT, 2) . "\n";
    echo "   IVA (16%): $" . number_format($ivaOT, 2) . "\n";
    echo "   Total: $" . number_format($totalOT, 2) . "\n";
});
