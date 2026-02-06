<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Test directo de la query
$servicio = \App\Models\OTServicio::find(54); // Transporte

echo "=== TEST DIRECTO QUERY ===\n\n";

// Método 1: usando items() (relación como query)
$faltante1 = $servicio->items()->sum('faltante');
echo "items()->sum('faltante'): $faltante1\n";

// Método 2: usando items (propiedad cargada)
$faltante2 = $servicio->items->sum('faltante');
echo "items->sum('faltante'): $faltante2\n";

// Método 3: verificar si existe la columna
$item = \App\Models\OTServicioItem::find(82);
echo "\n=== ITEM 82 ===\n";
echo "Attributes: " . json_encode($item->getAttributes()) . "\n";
echo "Faltante directo: " . ($item->faltante ?? 'NULL') . "\n";

// Método 4: Query raw
$raw = \DB::select("SELECT faltante FROM ot_servicio_items WHERE id = 82");
echo "\n=== RAW QUERY ===\n";
echo "Faltante (raw): " . ($raw[0]->faltante ?? 'NULL') . "\n";
