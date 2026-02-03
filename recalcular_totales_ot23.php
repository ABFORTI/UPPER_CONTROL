<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Orden;
use Illuminate\Support\Facades\DB;

DB::transaction(function() {
    $orden = Orden::find(23);
    
    if (!$orden) {
        echo "OT 23 no encontrada\n";
        return;
    }
    
    echo "Recalculando totales para OT #{$orden->id}\n";
    echo "===========================================\n\n";
    
    foreach ($orden->otServicios as $serv) {
        $subtotalItems = $serv->items()->sum('subtotal');
        $serv->subtotal = $subtotalItems;
        $serv->save();
        echo "Servicio #{$serv->id} ({$serv->servicio->nombre})\n";
        echo "  - Subtotal actualizado: $" . number_format($subtotalItems, 2) . "\n\n";
    }
    
    // Recalcular totales de la OT
    $subtotalTotal = $orden->otServicios()->sum('subtotal');
    $ivaTotal = $subtotalTotal * 0.16;
    $totalTotal = $subtotalTotal + $ivaTotal;
    
    $orden->subtotal = $subtotalTotal;
    $orden->iva = $ivaTotal;
    $orden->total = $totalTotal;
    $orden->total_real = $subtotalTotal;
    $orden->save();
    
    echo "Totales de la OT actualizados:\n";
    echo "  - Subtotal: $" . number_format($orden->subtotal, 2) . "\n";
    echo "  - IVA (16%): $" . number_format($orden->iva, 2) . "\n";
    echo "  - Total: $" . number_format($orden->total, 2) . "\n";
    echo "\n✅ Totales recalculados exitosamente\n";
});
