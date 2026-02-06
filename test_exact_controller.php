<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Simular exactamente lo que hace el controlador
use Inertia\Inertia;

$orden = \App\Models\Orden::with([
    'otServicios.servicio',
    'otServicios.items',
    'otServicios.avances.createdBy',
])->find(45);

echo "=== SIMULACIÓN EXACTA DEL CONTROLADOR ===\n\n";

$serviciosData = $orden->otServicios->map(function ($otServicio) {
    echo "Processing servicio ID: {$otServicio->id}\n";
    echo "  Items loaded: " . ($otServicio->relationLoaded('items') ? 'YES' : 'NO') . "\n";
    echo "  Items count: " . $otServicio->items->count() . "\n";
    
    $totales = $otServicio->calcularTotales();
    echo "  Totales: " . json_encode($totales) . "\n\n";
    
    return [
        'id' => $otServicio->id,
        'servicio' => $otServicio->servicio->only(['id', 'nombre']),
        'tipo_cobro' => $otServicio->tipo_cobro,
        'cantidad' => $otServicio->cantidad,
        'precio_unitario' => $otServicio->precio_unitario,
        'subtotal' => $otServicio->subtotal,
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

echo "\n=== DATOS FINALES QUE SE ENVÍAN ===\n";
echo json_encode($serviciosData->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
