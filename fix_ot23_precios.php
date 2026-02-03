<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Orden;
use App\Models\OTServicio;
use Illuminate\Support\Facades\DB;

DB::transaction(function() {
    echo "Corrigiendo precios de items de servicios...\n";
    echo "============================================\n\n";
    
    // Corregir item de Almacenaje (id 19)
    DB::table('ot_servicio_items')->where('id', 19)->update([
        'precio_unitario' => 10.00,
        'subtotal' => 100.00
    ]);
    echo "✓ Item 19 (Almacenaje) actualizado: precio_unitario=10.00, subtotal=100.00\n\n";
    
    // Recalcular subtotales de servicios
    $orden = Orden::find(23);
    foreach ($orden->otServicios as $serv) {
        $subtotalItems = $serv->items()->sum('subtotal');
        $serv->subtotal = $subtotalItems;
        $serv->save();
        echo "Servicio #{$serv->id} ({$serv->servicio->nombre}): subtotal = $" . number_format($subtotalItems, 2) . "\n";
    }
    
    echo "\nRecalculando totales de la OT...\n";
    $subtotalTotal = $orden->otServicios()->sum('subtotal');
    $ivaTotal = $subtotalTotal * 0.16;
    $totalTotal = $subtotalTotal + $ivaTotal;
    
    $orden->subtotal = $subtotalTotal;
    $orden->iva = $ivaTotal;
    $orden->total = $totalTotal;
    $orden->total_real = $subtotalTotal;
    $orden->save();
    
    echo "\n✅ Totales finales de la OT #23:\n";
    echo "   Subtotal: $" . number_format($orden->subtotal, 2) . "\n";
    echo "   IVA (16%): $" . number_format($orden->iva, 2) . "\n";
    echo "   Total: $" . number_format($orden->total, 2) . "\n";
});
