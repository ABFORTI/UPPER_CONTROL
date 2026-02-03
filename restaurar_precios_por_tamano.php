<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

DB::transaction(function() {
    echo "Restaurando precios individuales por tamaño...\n";
    echo "===============================================\n\n";
    
    // Obtener precios del catálogo
    $preciosTamano = DB::table('servicio_tamanos')
        ->where('id_servicio_centro', 1)
        ->get(['tamano', 'precio'])
        ->keyBy('tamano');
    
    echo "Precios del catálogo:\n";
    foreach ($preciosTamano as $tam => $data) {
        echo "  {$tam}: \$" . number_format($data->precio, 2) . "\n";
    }
    
    // Actualizar items con precios individuales
    $items = DB::table('ot_servicio_items')
        ->where('ot_servicio_id', 24)
        ->get(['id', 'tamano', 'planeado']);
    
    $subtotalServicio = 0;
    $totalCantidad = 0;
    $totalValor = 0;
    
    echo "\nActualizando items:\n";
    foreach ($items as $item) {
        $precio = $preciosTamano[$item->tamano]->precio ?? 0;
        $subtotal = $item->planeado * $precio;
        
        DB::table('ot_servicio_items')->where('id', $item->id)->update([
            'precio_unitario' => $precio,
            'subtotal' => $subtotal
        ]);
        
        $subtotalServicio += $subtotal;
        $totalCantidad += $item->planeado;
        $totalValor += $subtotal;
        
        echo "  Item {$item->id} ({$item->tamano}): {$item->planeado} x \${$precio} = \$" . number_format($subtotal, 2) . "\n";
    }
    
    // Calcular precio promedio ponderado para display
    $precioPonderado = $totalCantidad > 0 ? ($totalValor / $totalCantidad) : 0;
    
    // Actualizar servicio
    DB::table('ot_servicios')->where('id', 24)->update([
        'subtotal' => $subtotalServicio,
        'precio_unitario' => $precioPonderado
    ]);
    
    echo "\n✓ Servicio actualizado:\n";
    echo "  Subtotal: \$" . number_format($subtotalServicio, 2) . "\n";
    echo "  Precio promedio (display): \$" . number_format($precioPonderado, 2) . "\n\n";
    
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
    
    echo "✅ Totales de la OT 23:\n";
    echo "   Subtotal: \$" . number_format($subtotalOT, 2) . "\n";
    echo "   IVA (16%): \$" . number_format($ivaOT, 2) . "\n";
    echo "   Total: \$" . number_format($totalOT, 2) . "\n";
});
