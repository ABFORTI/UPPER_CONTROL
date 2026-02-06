<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Buscar el servicio de Almacenaje de la orden 48
$orden = \App\Models\Orden::with(['otServicios.servicio', 'otServicios.items', 'otServicios.avances'])->find(48);

if (!$orden) {
    echo "Orden 48 no encontrada\n";
    exit;
}

echo "=== DIAGNÓSTICO ORDEN #48 ===\n\n";

foreach ($orden->otServicios as $servicio) {
    if ($servicio->servicio->nombre !== 'Almacenaje') continue;
    
    echo "SERVICIO: Almacenaje (ID: {$servicio->id})\n";
    echo str_repeat("=", 60) . "\n\n";
    
    echo "ITEMS EN DB:\n";
    foreach ($servicio->items as $item) {
        echo "  Item ID: {$item->id}\n";
        echo "  Descripción: {$item->descripcion_item}\n";
        echo "  Planeado (DB): {$item->planeado}\n";
        echo "  Completado (DB): {$item->completado}\n";
        echo "  Faltante (DB): {$item->faltante}\n\n";
    }
    
    echo "AVANCES REGISTRADOS:\n";
    foreach ($servicio->avances as $avance) {
        $esFaltante = str_contains($avance->comentario ?? '', '[FALTANTES]');
        $tipo = $esFaltante ? 'FALTANTE' : 'PRODUCCIÓN';
        echo "  - [{$tipo}] Cant: {$avance->cantidad_registrada}, Comentario: {$avance->comentario}\n";
    }
    
    $avancesProduccion = $servicio->avances->filter(fn($a) => !str_contains($a->comentario ?? '', '[FALTANTES]'));
    $avancesFaltantes = $servicio->avances->filter(fn($a) => str_contains($a->comentario ?? '', '[FALTANTES]'));
    
    $sumaProduccion = $avancesProduccion->sum('cantidad_registrada');
    $sumaFaltantes = $avancesFaltantes->sum('cantidad_registrada');
    
    echo "\n";
    echo "SUMA AVANCES PRODUCCIÓN: {$sumaProduccion}\n";
    echo "SUMA AVANCES FALTANTES: {$sumaFaltantes}\n";
    echo "TOTAL PROCESADO: " . ($sumaProduccion + $sumaFaltantes) . "\n";
    echo "PENDIENTE: " . (10 - $sumaProduccion - $sumaFaltantes) . "\n";
    
    echo "\n❌ PROBLEMA: El campo 'completado' debería ser {$sumaProduccion} pero es {$servicio->items[0]->completado}\n";
    echo "❌ PROBLEMA: El campo 'faltante' debería ser {$sumaFaltantes} pero es {$servicio->items[0]->faltante}\n";
}
