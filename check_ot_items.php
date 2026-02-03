<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

$otId = 11;

$ot = \App\Models\OrdenTrabajo::with(['otServicios.servicio', 'otServicios.items'])->find($otId);

if (!$ot) {
    echo "❌ OT #{$otId} no encontrada\n";
    exit(1);
}

echo "✅ OT #{$ot->id} encontrada\n";
echo "Servicios: " . $ot->otServicios->count() . "\n\n";

foreach ($ot->otServicios as $idx => $otServicio) {
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "🔹 Servicio #" . ($idx + 1) . "\n";
    echo "   ID: {$otServicio->id}\n";
    echo "   Nombre: " . ($otServicio->servicio->nombre ?? 'N/A') . "\n";
    echo "   Cantidad: {$otServicio->cantidad}\n";
    echo "   Items: " . $otServicio->items->count() . "\n";
    
    if ($otServicio->items->count() > 0) {
        foreach ($otServicio->items as $item) {
            echo "     📦 {$item->descripcion_item}\n";
            echo "        Planeado: {$item->planeado} | Completado: {$item->completado}\n";
        }
    } else {
        echo "     ⚠️ No hay items registrados\n";
    }
    echo "\n";
}
