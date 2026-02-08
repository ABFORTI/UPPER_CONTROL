<?php
require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Buscar el servicio de Surtido en esta OT
$servicioSurtido = \App\Models\OTServicio::with('items')
    ->whereHas('servicio', fn($q) => $q->where('nombre', 'like', '%Surtido%'))
    ->latest()
    ->first();

if ($servicioSurtido) {
    echo "=== SERVICIO SURTIDO (ID: {$servicioSurtido->id}) ===\n";
    echo "OT: #{$servicioSurtido->ot_id}\n";
    echo "Servicio: {$servicioSurtido->servicio->nombre}\n\n";
    
    echo "ITEMS:\n";
    foreach ($servicioSurtido->items as $item) {
        echo "  - ID: {$item->id}\n";
        echo "    descripcion_item: " . ($item->descripcion_item ?? 'NULL') . "\n";
        echo "    tamano: " . ($item->tamano ?? 'NULL') . "\n";
        echo "    planeado: {$item->planeado}\n";
        echo "    ---\n";
    }
} else {
    echo "No se encontró servicio de Surtido\n";
}
