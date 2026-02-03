<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

DB::transaction(function() {
    echo "Calculando precio promedio ponderado para servicio Distribución...\n";
    echo "===================================================================\n\n";
    
    // Obtener items actuales del servicio con sus cantidades
    $items = DB::table('ot_servicio_items')
        ->where('ot_servicio_id', 24)
        ->get(['tamano', 'planeado']);
    
    // Obtener precios por tamaño del catálogo
    $preciosTamano = DB::table('servicio_tamanos')
        ->where('id_servicio_centro', 1)
        ->get(['tamano', 'precio'])
        ->keyBy('tamano');
    
    $totalCantidad = 0;
    $totalValor = 0;
    
    echo "Distribución actual:\n";
    foreach ($items as $item) {
        $precio = $preciosTamano[$item->tamano]->precio ?? 0;
        $valor = $item->planeado * $precio;
        $totalCantidad += $item->planeado;
        $totalValor += $valor;
        
        echo "  {$item->tamano}: {$item->planeado} unidades x \${$precio} = \${$valor}\n";
    }
    
    $precioPonderado = $totalCantidad > 0 ? ($totalValor / $totalCantidad) : 0;
    
    echo "\nPrecio promedio ponderado: \$" . number_format($precioPonderado, 2) . "\n";
    echo "  (Total: \${$totalValor} / {$totalCantidad} unidades)\n\n";
    
    // Actualizar precio_unitario del servicio
    DB::table('ot_servicios')->where('id', 24)->update([
        'precio_unitario' => $precioPonderado
    ]);
    
    echo "✓ Precio unitario del servicio actualizado a: \$" . number_format($precioPonderado, 2) . "\n\n";
    
    // Ahora actualizar items y subtotales con este precio único
    $subtotalServicio = 0;
    echo "Actualizando items con precio único:\n";
    
    foreach ($items as $item) {
        $nuevoSubtotal = $item->planeado * $precioPonderado;
        
        DB::table('ot_servicio_items')
            ->where('ot_servicio_id', 24)
            ->where('tamano', $item->tamano)
            ->update([
                'precio_unitario' => $precioPonderado,
                'subtotal' => $nuevoSubtotal
            ]);
        
        $subtotalServicio += $nuevoSubtotal;
        
        echo "  {$item->tamano}: {$item->planeado} x \$" . number_format($precioPonderado, 2) . " = \$" . number_format($nuevoSubtotal, 2) . "\n";
    }
    
    // Actualizar subtotal del servicio
    DB::table('ot_servicios')->where('id', 24)->update([
        'subtotal' => $subtotalServicio
    ]);
    
    echo "\n✓ Subtotal del servicio: \$" . number_format($subtotalServicio, 2) . "\n\n";
    
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
