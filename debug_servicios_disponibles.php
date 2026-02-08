<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$orden = \App\Models\Orden::find(53);
echo "=== DEBUG SERVICIOS DISPONIBLES ===\n\n";
echo "OT #53\n";
echo "Centro: {$orden->id_centrotrabajo}\n\n";

$servicios = \App\Models\ServicioCentro::where('id_centrotrabajo', $orden->id_centrotrabajo)
    ->with('servicio')
    ->get();

echo "Servicios encontrados: " . $servicios->count() . "\n\n";

foreach ($servicios as $sc) {
    echo "ServicioCentro ID: {$sc->id}\n";
    echo "  servicio_id: " . ($sc->servicio_id ?? 'NULL') . "\n";
    echo "  id_servicio: " . ($sc->id_servicio ?? 'NULL') . "\n";
    echo "  Servicio nombre: " . ($sc->servicio->nombre ?? 'N/A') . "\n";
    echo "  Precio base: " . ($sc->precio_base ?? '0') . "\n\n";
}

echo "\n=== USANDO EL MÉTODO DEL CONTROLADOR ===\n";
$controller = new \App\Http\Controllers\OrdenController();
$reflection = new \ReflectionMethod($controller, 'getServiciosDisponibles');
$reflection->setAccessible(true);
$resultado = $reflection->invoke($controller, $orden->id_centrotrabajo);

echo "Resultado del método:\n";
print_r($resultado);
