<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== BUSCANDO SERVICIO TRANSPORTE CON AVANCES ===\n\n";

$servicios = \App\Models\OTServicio::with(['servicio', 'items', 'avances'])
    ->whereHas('servicio', function($q) {
        $q->where('nombre', 'Transporte');
    })
    ->get();

foreach ($servicios as $serv) {
    $serv->load('orden');
    $orden = $serv->orden;
    if (!$orden) continue;
    
    echo "Servicio ID: {$serv->id} | Orden: {$orden->id} | Avances: {$serv->avances->count()}\n";
    
    if ($serv->avances->count() >= 2) {
        echo "\n=== ESTE ES EL CORRECTO ===\n";
        echo "Orden ID: {$orden->id}\n";
        echo "Servicio ID: {$serv->id}\n";
        echo "Items: {$serv->items->count()}\n\n";
        
        foreach ($serv->items as $item) {
            echo "ITEM {$item->id}:\n";
            echo "  Planeado: {$item->planeado}\n";
            echo "  Completado (DB): {$item->completado}\n";
            echo "  Faltante (DB): {$item->faltante}\n";
        }
        
        echo "\nAVANCES:\n";
        foreach ($serv->avances as $avance) {
            echo "  - Cantidad: {$avance->cantidad_registrada}\n";
        }
        
        echo "\nSuma avances: {$serv->avances->sum('cantidad_registrada')}\n";
        
        // Calcular totales
        $totales = $serv->calcularTotales();
        echo "\nTOTALES CALCULADOS:\n";
        print_r($totales);
    }
}
