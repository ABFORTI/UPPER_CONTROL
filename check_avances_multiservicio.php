<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\OTServicioAvance;
use App\Models\OTServicio;

echo "=== Verificando avances de multi-servicio ===\n\n";

// Obtener los últimos 10 avances
$avances = OTServicioAvance::with(['otServicio.servicio'])
    ->orderBy('id', 'desc')
    ->take(10)
    ->get();

foreach ($avances as $avance) {
    echo "ID: {$avance->id}\n";
    echo "Servicio: {$avance->otServicio->servicio->nombre}\n";
    echo "OT Servicio ID: {$avance->ot_servicio_id}\n";
    echo "Tarifa: {$avance->tarifa}\n";
    echo "Precio aplicado: " . ($avance->precio_unitario_aplicado ?? 'NULL') . "\n";
    echo "Cantidad: {$avance->cantidad_registrada}\n";
    echo "Subtotal del servicio: {$avance->otServicio->subtotal}\n";
    echo "Precio unitario del servicio: {$avance->otServicio->precio_unitario}\n";
    echo "---\n";
}

// Verificar si hay avances sin precio_unitario_aplicado
$sinPrecio = OTServicioAvance::whereNull('precio_unitario_aplicado')
    ->orWhere('precio_unitario_aplicado', 0)
    ->count();

echo "\n\nAvances sin precio_unitario_aplicado: {$sinPrecio}\n";

// Mostrar recálculo para un servicio específico
if ($avances->count() > 0) {
    $primerAvance = $avances->first();
    $servicioId = $primerAvance->ot_servicio_id;
    
    echo "\n=== Recálculo para OT Servicio ID: {$servicioId} ===\n";
    
    $todosAvances = OTServicioAvance::where('ot_servicio_id', $servicioId)->get();
    $subtotalCalculado = 0;
    
    foreach ($todosAvances as $av) {
        $cantidad = (int)$av->cantidad_registrada;
        $precio = (float)($av->precio_unitario_aplicado ?? 0);
        $subtotal = $cantidad * $precio;
        
        echo "Avance ID {$av->id}: {$cantidad} × \${$precio} = \${$subtotal}\n";
        $subtotalCalculado += $subtotal;
    }
    
    echo "Subtotal calculado: \${$subtotalCalculado}\n";
    echo "Subtotal en BD: \${$primerAvance->otServicio->subtotal}\n";
}
