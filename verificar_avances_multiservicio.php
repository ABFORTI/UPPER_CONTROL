<?php

/**
 * Script de Verificación: Avances Multi-Servicio
 * 
 * Verifica que:
 * 1. No hay duplicados en ot_servicio_avances
 * 2. Los subtotales de servicios coinciden con la suma de avances
 * 3. Los totales de la orden coinciden con la suma de subtotales de servicios
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "\n=== VERIFICACIÓN DE AVANCES MULTI-SERVICIO ===\n\n";

// 1. Verificar duplicados por request_id
echo "1️⃣ Verificando duplicados por request_id...\n";
$duplicadosRequest = DB::table('ot_servicio_avances')
    ->select('request_id', DB::raw('COUNT(*) as count'))
    ->whereNotNull('request_id')
    ->groupBy('request_id')
    ->having('count', '>', 1)
    ->get();

if ($duplicadosRequest->isEmpty()) {
    echo "   ✅ No hay duplicados por request_id\n";
} else {
    echo "   ❌ ENCONTRADOS {$duplicadosRequest->count()} request_id duplicados:\n";
    foreach ($duplicadosRequest as $dup) {
        echo "      - request_id: {$dup->request_id} (aparece {$dup->count} veces)\n";
    }
}

// 2. Verificar duplicados por contenido (mismo servicio, tarifa, cantidad, precio, fecha)
echo "\n2️⃣ Verificando duplicados por contenido...\n";
$duplicadosContenido = DB::select("
    SELECT 
        ot_servicio_id,
        tarifa,
        cantidad_registrada,
        precio_unitario_aplicado,
        DATE(created_at) as fecha,
        COUNT(*) as count
    FROM ot_servicio_avances
    GROUP BY ot_servicio_id, tarifa, cantidad_registrada, precio_unitario_aplicado, DATE(created_at)
    HAVING count > 1
");

if (empty($duplicadosContenido)) {
    echo "   ✅ No hay duplicados por contenido\n";
} else {
    echo "   ⚠️ ENCONTRADOS " . count($duplicadosContenido) . " grupos de avances con contenido idéntico:\n";
    foreach ($duplicadosContenido as $dup) {
        echo "      - Servicio #{$dup->ot_servicio_id} | {$dup->tarifa} | Cant: {$dup->cantidad_registrada} | ";
        echo "PU: \${$dup->precio_unitario_aplicado} | Fecha: {$dup->fecha} ({$dup->count} registros)\n";
    }
}

// 3. Verificar que los subtotales de servicios coinciden con la suma de avances
echo "\n3️⃣ Verificando subtotales de servicios...\n";
$servicios = DB::table('ot_servicios as s')
    ->join('ordenes_trabajo as o', 's.ot_id', '=', 'o.id')
    ->select('s.id', 's.ot_id', 's.subtotal', 'o.id as orden_id')
    ->get();

$erroresSubtotal = [];
foreach ($servicios as $serv) {
    $sumaAvances = DB::table('ot_servicio_avances')
        ->where('ot_servicio_id', $serv->id)
        ->get()
        ->sum(function($av) {
            return $av->cantidad_registrada * $av->precio_unitario_aplicado;
        });
    
    $sumaAvances = round($sumaAvances, 2);
    $subtotalServicio = round($serv->subtotal, 2);
    
    if (abs($sumaAvances - $subtotalServicio) > 0.01) {
        $erroresSubtotal[] = [
            'ot_id' => $serv->orden_id,
            'servicio_id' => $serv->id,
            'subtotal_db' => $subtotalServicio,
            'suma_avances' => $sumaAvances,
            'diferencia' => $subtotalServicio - $sumaAvances,
        ];
    }
}

if (empty($erroresSubtotal)) {
    echo "   ✅ Todos los subtotales de servicios coinciden con la suma de avances\n";
} else {
    echo "   ❌ ENCONTRADOS " . count($erroresSubtotal) . " servicios con subtotales incorrectos:\n";
    foreach ($erroresSubtotal as $err) {
        echo "      - OT #{$err['ot_id']} | Servicio #{$err['servicio_id']}\n";
        echo "        Subtotal en DB: \${$err['subtotal_db']}\n";
        echo "        Suma de avances: \${$err['suma_avances']}\n";
        echo "        Diferencia: \${$err['diferencia']}\n";
    }
}

// 4. Verificar que los totales de órdenes coinciden con la suma de subtotales de servicios
echo "\n4️⃣ Verificando totales de órdenes...\n";
$ordenes = DB::table('ordenes_trabajo')
    ->whereExists(function($query) {
        $query->select(DB::raw(1))
              ->from('ot_servicios')
              ->whereColumn('ot_servicios.ot_id', 'ordenes_trabajo.id');
    })
    ->select('id', 'subtotal', 'iva', 'total')
    ->get();

$erroresTotales = [];
foreach ($ordenes as $orden) {
    $sumaSubtotalesServicios = DB::table('ot_servicios')
        ->where('ot_id', $orden->id)
        ->sum('subtotal');
    
    $sumaSubtotalesServicios = round($sumaSubtotalesServicios, 2);
    $subtotalOrden = round($orden->subtotal, 2);
    $ivaCalculado = round($sumaSubtotalesServicios * 0.16, 2);
    $totalCalculado = $sumaSubtotalesServicios + $ivaCalculado;
    
    if (abs($subtotalOrden - $sumaSubtotalesServicios) > 0.01 || 
        abs($orden->total - $totalCalculado) > 0.01) {
        $erroresTotales[] = [
            'ot_id' => $orden->id,
            'subtotal_db' => $subtotalOrden,
            'suma_servicios' => $sumaSubtotalesServicios,
            'total_db' => round($orden->total, 2),
            'total_calculado' => $totalCalculado,
        ];
    }
}

if (empty($erroresTotales)) {
    echo "   ✅ Todos los totales de órdenes coinciden con la suma de subtotales de servicios\n";
} else {
    echo "   ❌ ENCONTRADAS " . count($erroresTotales) . " órdenes con totales incorrectos:\n";
    foreach ($erroresTotales as $err) {
        echo "      - OT #{$err['ot_id']}\n";
        echo "        Subtotal en DB: \${$err['subtotal_db']} | Suma servicios: \${$err['suma_servicios']}\n";
        echo "        Total en DB: \${$err['total_db']} | Total calculado: \${$err['total_calculado']}\n";
    }
}

// 5. Resumen de avances por orden
echo "\n5️⃣ Resumen de avances por orden multi-servicio...\n";
$resumen = DB::select("
    SELECT 
        o.id as orden_id,
        COUNT(DISTINCT s.id) as num_servicios,
        COUNT(a.id) as num_avances,
        SUM(a.cantidad_registrada) as cantidad_total,
        SUM(a.cantidad_registrada * a.precio_unitario_aplicado) as total_avances
    FROM ordenes_trabajo o
    JOIN ot_servicios s ON s.ot_id = o.id
    LEFT JOIN ot_servicio_avances a ON a.ot_servicio_id = s.id
    GROUP BY o.id
    HAVING num_avances > 0
    ORDER BY o.id DESC
    LIMIT 10
");

if (empty($resumen)) {
    echo "   ℹ️ No hay órdenes multi-servicio con avances\n";
} else {
    echo "   📊 Últimas 10 órdenes multi-servicio:\n";
    foreach ($resumen as $r) {
        echo "      - OT #{$r->orden_id}: {$r->num_servicios} servicio(s), ";
        echo "{$r->num_avances} avance(s), {$r->cantidad_total} unidades totales, ";
        echo "\$" . number_format($r->total_avances, 2) . "\n";
    }
}

// 6. Verificar constraint único
echo "\n6️⃣ Verificando constraint único (ot_servicio_id, request_id)...\n";
$constraint = DB::select("
    SHOW INDEX FROM ot_servicio_avances 
    WHERE Key_name = 'uk_servicio_request'
");

if (empty($constraint)) {
    echo "   ❌ Constraint único NO existe (uk_servicio_request)\n";
} else {
    echo "   ✅ Constraint único existe (uk_servicio_request)\n";
}

// 7. Verificar columna request_id
echo "\n7️⃣ Verificando columna request_id...\n";
$columna = DB::select("
    SHOW COLUMNS FROM ot_servicio_avances 
    WHERE Field = 'request_id'
");

if (empty($columna)) {
    echo "   ❌ Columna request_id NO existe\n";
} else {
    echo "   ✅ Columna request_id existe\n";
    
    // Verificar cuántos avances tienen request_id
    $conRequestId = DB::table('ot_servicio_avances')
        ->whereNotNull('request_id')
        ->count();
    $sinRequestId = DB::table('ot_servicio_avances')
        ->whereNull('request_id')
        ->count();
    
    echo "      - Con request_id: {$conRequestId}\n";
    echo "      - Sin request_id: {$sinRequestId}\n";
}

echo "\n=== FIN DE VERIFICACIÓN ===\n\n";
