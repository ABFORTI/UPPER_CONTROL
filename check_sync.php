<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$item = \App\Models\OTServicioItem::find(82);
$servicio = $item->otServicio;
$avances = $servicio->avances;

echo "=== DIAGNÓSTICO TRANSPORTE ===\n\n";
echo "ITEM EN DB:\n";
echo "  ID: {$item->id}\n";
echo "  Planeado: {$item->planeado}\n";
echo "  Completado (campo DB): {$item->completado}\n";
echo "  Faltante (campo DB): {$item->faltante}\n\n";

echo "AVANCES REGISTRADOS:\n";
echo "  Total avances: {$avances->count()}\n";
echo "  Suma cantidad_registrada: {$avances->sum('cantidad_registrada')}\n\n";

echo "DETALLE AVANCES:\n";
foreach ($avances as $avance) {
    echo "  - ID {$avance->id}: cantidad={$avance->cantidad_registrada}, comentario={$avance->comentario}\n";
}

echo "\n❌ PROBLEMA: El campo 'completado' en ot_servicio_items NO se está actualizando cuando se registran avances.\n";
