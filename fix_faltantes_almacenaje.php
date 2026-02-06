<?php

/**
 * Script para recalcular correctamente los faltantes desde los avances
 * Problema: El campo faltante en ot_servicio_items está mal calculado
 * Solución: Recalcular desde OTServicioAvance con [FALTANTES] en el comentario
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "🔧 CORRECCIÓN DE FALTANTES - SERVICIO ID 57\n";
echo str_repeat("=", 70) . "\n\n";

// 1. Usar directamente el ID del servicio que identificamos
$servicioId = 57;

$servicio = DB::table('ot_servicios')
    ->where('id', $servicioId)
    ->first();

if (!$servicio) {
    echo "❌ No se encontró el servicio con ID {$servicioId}\n";
    exit(1);
}

echo "✅ Servicio encontrado: ID {$servicio->id}\n\n";

// 2. Obtener items del servicio
$items = DB::table('ot_servicio_items')
    ->where('ot_servicio_id', $servicio->id)
    ->get();

echo "📦 Items del servicio:\n";
echo str_repeat("-", 70) . "\n";

foreach ($items as $item) {
    echo "Item ID: {$item->id}\n";
    echo "  Descripción: {$item->descripcion_item}\n";
    echo "  Planeado: {$item->planeado}\n";
    echo "  Completado: {$item->completado}\n";
    echo "  Faltante (actual): {$item->faltante}\n";
    
    // 3. Calcular faltantes REALES desde los avances
    $faltantesReales = DB::table('ot_servicio_avances')
        ->where('ot_servicio_id', $servicio->id)
        ->where('comentario', 'LIKE', '%[FALTANTES]%')
        ->where('comentario', 'LIKE', "%{$item->descripcion_item}%")
        ->sum('cantidad_registrada');
    
    echo "  Faltantes (desde avances): {$faltantesReales}\n";
    
    // 4. Actualizar si es diferente
    if ($item->faltante != $faltantesReales) {
        DB::table('ot_servicio_items')
            ->where('id', $item->id)
            ->update(['faltante' => $faltantesReales]);
        echo "  ✅ Actualizado: {$item->faltante} → {$faltantesReales}\n";
    } else {
        echo "  ✓ Ya está correcto\n";
    }
    
    echo "\n";
}

echo str_repeat("=", 70) . "\n";
echo "✅ Corrección completada\n";
