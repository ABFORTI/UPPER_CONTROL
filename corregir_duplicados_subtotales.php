<?php

/**
 * Script para Corregir Duplicados y Subtotales
 * 
 * 1. Limpia avances duplicados (mantiene el más reciente)
 * 2. Recalcula subtotales de servicios desde los avances
 * 3. Recalcula totales de órdenes desde los servicios
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "\n=== CORRECCIÓN DE DUPLICADOS Y SUBTOTALES ===\n\n";

// 1. Identificar y eliminar duplicados
echo "1️⃣ Identificando duplicados...\n";

$duplicados = DB::select("
    SELECT 
        ot_servicio_id,
        tarifa,
        cantidad_registrada,
        precio_unitario_aplicado,
        DATE(created_at) as fecha,
        created_at,
        GROUP_CONCAT(id ORDER BY id) as ids,
        COUNT(*) as count
    FROM ot_servicio_avances
    GROUP BY ot_servicio_id, tarifa, cantidad_registrada, precio_unitario_aplicado, DATE(created_at), created_at
    HAVING count > 1
");

if (empty($duplicados)) {
    echo "   ✅ No hay duplicados para limpiar\n";
} else {
    echo "   ⚠️ ENCONTRADOS " . count($duplicados) . " grupos de avances duplicados\n\n";
    
    foreach ($duplicados as $dup) {
        $ids = explode(',', $dup->ids);
        $mantener = array_pop($ids); // Mantener el más reciente (último ID)
        $eliminar = $ids;
        
        echo "   📦 Servicio #{$dup->ot_servicio_id} | {$dup->tarifa} | Cant: {$dup->cantidad_registrada} | ";
        echo "PU: \${$dup->precio_unitario_aplicado} | {$dup->fecha}\n";
        echo "      - Total: {$dup->count} registros (IDs: {$dup->ids})\n";
        echo "      - Mantener: ID {$mantener}\n";
        echo "      - Eliminar: IDs " . implode(', ', $eliminar) . "\n\n";
    }
    
    echo "¿Deseas eliminar los duplicados? (escribe SI para confirmar): ";
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    fclose($handle);
    
    if (trim($line) !== 'SI') {
        echo "   ❌ Operación cancelada\n";
        exit(0);
    }
    
    echo "\n   🗑️ Eliminando duplicados...\n";
    $totalEliminados = 0;
    
    foreach ($duplicados as $dup) {
        $ids = explode(',', $dup->ids);
        $mantener = array_pop($ids); // Mantener el más reciente
        $eliminar = $ids;
        
        if (!empty($eliminar)) {
            $deleted = DB::table('ot_servicio_avances')
                ->whereIn('id', $eliminar)
                ->delete();
            $totalEliminados += $deleted;
            echo "      ✅ Eliminados {$deleted} duplicados del grupo (Servicio #{$dup->ot_servicio_id})\n";
        }
    }
    
    echo "\n   ✅ Total eliminados: {$totalEliminados} avances duplicados\n";
}

// 2. Recalcular subtotales de servicios
echo "\n2️⃣ Recalculando subtotales de servicios...\n";

$servicios = DB::table('ot_servicios')->get();
$actualizados = 0;

foreach ($servicios as $servicio) {
    $sumaAvances = DB::table('ot_servicio_avances')
        ->where('ot_servicio_id', $servicio->id)
        ->get()
        ->sum(function($av) {
            return $av->cantidad_registrada * $av->precio_unitario_aplicado;
        });
    
    $sumaAvances = round($sumaAvances, 2);
    $subtotalActual = round($servicio->subtotal, 2);
    
    // Solo actualizar si hay avances o si el subtotal es diferente
    if ($sumaAvances > 0 || abs($subtotalActual - $sumaAvances) > 0.01) {
        DB::table('ot_servicios')
            ->where('id', $servicio->id)
            ->update(['subtotal' => $sumaAvances]);
        
        if (abs($subtotalActual - $sumaAvances) > 0.01) {
            echo "   📝 Servicio #{$servicio->id}: \${$subtotalActual} → \${$sumaAvances}\n";
            $actualizados++;
        }
    }
}

echo "   ✅ Actualizados {$actualizados} subtotales de servicios\n";

// 3. Recalcular totales de órdenes multi-servicio
echo "\n3️⃣ Recalculando totales de órdenes...\n";

$ordenesMultiServicio = DB::table('ordenes_trabajo')
    ->whereExists(function($query) {
        $query->select(DB::raw(1))
              ->from('ot_servicios')
              ->whereColumn('ot_servicios.ot_id', 'ordenes_trabajo.id');
    })
    ->get();

$ordenesActualizadas = 0;

foreach ($ordenesMultiServicio as $orden) {
    $subtotalServicios = DB::table('ot_servicios')
        ->where('ot_id', $orden->id)
        ->sum('subtotal');
    
    $subtotalServicios = round($subtotalServicios, 2);
    $iva = round($subtotalServicios * 0.16, 2);
    $total = $subtotalServicios + $iva;
    
    $subtotalActual = round($orden->subtotal, 2);
    $totalActual = round($orden->total, 2);
    
    if (abs($subtotalActual - $subtotalServicios) > 0.01 || abs($totalActual - $total) > 0.01) {
        DB::table('ordenes_trabajo')
            ->where('id', $orden->id)
            ->update([
                'subtotal' => $subtotalServicios,
                'iva' => $iva,
                'total' => $total,
                'total_real' => $subtotalServicios,
            ]);
        
        echo "   📝 Orden #{$orden->id}:\n";
        echo "      Subtotal: \${$subtotalActual} → \${$subtotalServicios}\n";
        echo "      Total: \${$totalActual} → \${$total}\n";
        $ordenesActualizadas++;
    }
}

echo "   ✅ Actualizadas {$ordenesActualizadas} órdenes\n";

echo "\n=== CORRECCIÓN COMPLETADA ===\n";
echo "\n✅ Resumen:\n";
echo "   - Duplicados eliminados: " . ($totalEliminados ?? 0) . "\n";
echo "   - Subtotales de servicios actualizados: {$actualizados}\n";
echo "   - Totales de órdenes actualizados: {$ordenesActualizadas}\n";
echo "\n💡 Ejecuta verificar_avances_multiservicio.php para confirmar\n\n";
