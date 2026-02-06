<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

// Simular lo que hace el controlador
$orden = \App\Models\Orden::with([
    'otServicios.servicio',
    'otServicios.items',
    'otServicios.avances.createdBy',
])->find(45);

if (!$orden) {
    echo "Orden no encontrada\n";
    exit;
}

echo "=== DATOS QUE SE ENVÍAN AL FRONTEND ===\n\n";

$serviciosData = $orden->otServicios->map(function ($otServicio) {
    $totales = $otServicio->calcularTotales();
    
    return [
        'id' => $otServicio->id,
        'servicio' => $otServicio->servicio->only(['id', 'nombre']),
        'planeado' => $totales['planeado'],
        'completado' => $totales['completado'],
        'faltantes_registrados' => $totales['faltantes_registrados'],
        'pendiente' => $totales['pendiente'],
        'progreso' => $totales['progreso'],
        'total' => $totales['total'],
        'items' => $otServicio->items->map(function ($item) {
            $planeado = (int)$item->planeado;
            $completado = (int)$item->completado;
            $faltantesRegistrados = (int)$item->faltante;
            $pendiente = max(0, $planeado - ($completado + $faltantesRegistrados));
            $progreso = $planeado > 0 
                ? round((($completado + $faltantesRegistrados) / $planeado) * 100) 
                : 0;
            
            return [
                'id' => $item->id,
                'descripcion_item' => $item->descripcion_item,
                'planeado' => $planeado,
                'completado' => $completado,
                'faltantes_registrados' => $faltantesRegistrados,
                'pendiente' => $pendiente,
                'progreso' => $progreso,
            ];
        })->toArray(),
    ];
});

echo json_encode($serviciosData->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
