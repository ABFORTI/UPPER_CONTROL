<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== SERVICIOS EN OT #53 ===\n\n";

$orden = \App\Models\Orden::find(53);
echo "Servicio tradicional: " . ($orden->id_servicio ?? 'N/A') . "\n\n";

$servicios = \App\Models\OTServicio::where('ot_id', 53)
    ->with(['servicio', 'addedBy'])
    ->get();

echo "Servicios en ot_servicios: " . $servicios->count() . "\n\n";

foreach ($servicios as $s) {
    echo "ID: {$s->id}\n";
    echo "  Servicio: " . ($s->servicio->nombre ?? 'N/A') . "\n";
    echo "  Origen: {$s->origen}\n";
    echo "  Cantidad: {$s->cantidad}\n";
    echo "  Agregado por: " . ($s->addedBy->name ?? 'Sistema') . "\n";
    echo "  Nota: " . ($s->nota ?? 'N/A') . "\n";
    echo "  Fecha: {$s->created_at}\n\n";
}
