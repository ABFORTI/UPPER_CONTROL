<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Test con orden 45
$orden = \App\Models\Orden::with(['otServicios.items', 'otServicios.servicio'])->find(45);

if (!$orden) {
    echo "No se encontró la orden 45\n";
    exit;
}

echo "=== ORDEN #{$orden->id} ===\n\n";

foreach ($orden->otServicios as $servicio) {
    echo "SERVICIO: {$servicio->servicio->nombre} (ID: {$servicio->id})\n";
    echo str_repeat("=", 60) . "\n\n";
    
    // Totales del servicio
    $totales = $servicio->calcularTotales();
    
    echo "TOTALES DEL SERVICIO:\n";
    echo "  Planeado: {$totales['planeado']}\n";
    echo "  Completado: {$totales['completado']}\n";
    echo "  Faltantes Registrados: {$totales['faltantes_registrados']}\n";
    echo "  Pendiente: {$totales['pendiente']}\n";
    echo "  Progreso: {$totales['progreso']}%\n";
    echo "  Total: {$totales['total']}\n\n";
    
    // Validación
    $suma = $totales['completado'] + $totales['faltantes_registrados'] + $totales['pendiente'];
    $valido = ($suma == $totales['planeado']) ? '✓ OK' : '✗ ERROR';
    echo "VALIDACIÓN: {$totales['completado']} + {$totales['faltantes_registrados']} + {$totales['pendiente']} = {$suma} ($valido)\n\n";
    
    echo "ITEMS:\n";
    foreach ($servicio->items as $item) {
        $planeado = (int)$item->planeado;
        $completado = (int)$item->completado;
        $faltantesRegistrados = (int)$item->faltante;
        $pendiente = max(0, $planeado - ($completado + $faltantesRegistrados));
        $progreso = $planeado > 0 
            ? round((($completado + $faltantesRegistrados) / $planeado) * 100) 
            : 0;
        
        echo "  - {$item->descripcion_item}\n";
        echo "    Planeado: {$planeado}\n";
        echo "    Completado: {$completado}\n";
        echo "    Faltantes: {$faltantesRegistrados}\n";
        echo "    Pendiente: {$pendiente}\n";
        echo "    Progreso: {$progreso}%\n";
        
        $suma_item = $completado + $faltantesRegistrados + $pendiente;
        $valido_item = ($suma_item == $planeado) ? '✓' : '✗';
        echo "    Validación: {$completado} + {$faltantesRegistrados} + {$pendiente} = {$suma_item} {$valido_item}\n\n";
    }
    
    echo "\n";
}
