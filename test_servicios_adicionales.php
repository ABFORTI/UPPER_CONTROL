<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== VERIFICACIÓN DE SERVICIOS ADICIONALES ===\n\n";

// 1. Verificar tabla ot_servicios
echo "1. Verificando estructura de tabla ot_servicios...\n";
try {
    $columns = DB::select("SHOW COLUMNS FROM ot_servicios WHERE Field IN ('origen', 'added_by_user_id', 'nota')");
    if (count($columns) === 3) {
        echo "   ✅ Tabla tiene las columnas necesarias: origen, added_by_user_id, nota\n";
    } else {
        echo "   ❌ FALTAN columnas en ot_servicios\n";
    }
} catch (\Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

// 2. Verificar últimos servicios adicionales
echo "\n2. Últimos 5 servicios ADICIONALES registrados:\n";
try {
    $servicios = \App\Models\OTServicio::where('origen', 'ADICIONAL')
        ->with(['addedBy:id,name', 'servicio:id,nombre'])
        ->latest()
        ->take(5)
        ->get();
    
    if ($servicios->count() === 0) {
        echo "   ⚠️  No hay servicios adicionales registrados aún\n";
    } else {
        foreach ($servicios as $s) {
            echo sprintf(
                "   - OT #%d | Servicio: %s | Agregado por: %s | Fecha: %s\n",
                $s->ot_id,
                $s->servicio->nombre ?? 'N/A',
                $s->addedBy->name ?? 'N/A',
                $s->created_at->format('d/m/Y H:i')
            );
            if ($s->nota) {
                echo "     Nota: " . substr($s->nota, 0, 60) . "...\n";
            }
        }
    }
} catch (\Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

// 3. Verificar ruta
echo "\n3. Verificando ruta...\n";
try {
    $route = Route::getRoutes()->getByName('ordenes.agregarServicioAdicional');
    if ($route) {
        echo "   ✅ Ruta existe: " . $route->methods()[0] . " " . $route->uri() . "\n";
        echo "   ✅ Middleware: " . implode(', ', $route->gatherMiddleware()) . "\n";
    } else {
        echo "   ❌ Ruta 'ordenes.agregarServicioAdicional' NO encontrada\n";
    }
} catch (\Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

// 4. Verificar método del controlador
echo "\n4. Verificando método del controlador...\n";
try {
    $reflection = new \ReflectionMethod(\App\Http\Controllers\OrdenController::class, 'agregarServicioAdicional');
    echo "   ✅ Método 'agregarServicioAdicional' existe en OrdenController\n";
    echo "   ✅ Parámetros: " . $reflection->getNumberOfParameters() . " (Request, Orden)\n";
} catch (\Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== FIN DE VERIFICACIÓN ===\n";
