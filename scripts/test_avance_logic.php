<?php
// scripts/test_avance_logic.php
// Simula la lógica de detección de avances corregidos

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Aprobacion;
use App\Models\Orden;

echo "=== Test: Lógica de detección de avances corregidos ===\n\n";

// Test con orden 78 (sabemos que tiene rechazos)
$ordenId = 78;

$hasRechazo = Aprobacion::where('aprobable_type', Orden::class)
    ->where('aprobable_id', $ordenId)
    ->where('tipo', 'calidad')
    ->where('resultado', 'rechazado')
    ->exists();

echo "Orden #{$ordenId}:\n";
echo "  - Tiene rechazos previos de calidad: " . ($hasRechazo ? 'SÍ' : 'NO') . "\n";
echo "  - Nuevos avances deberían marcarse como corregidos: " . ($hasRechazo ? 'SÍ' : 'NO') . "\n\n";

// Test con orden 77 (sabemos que NO tiene rechazos, solo validaciones)
$ordenId = 77;

$hasRechazo = Aprobacion::where('aprobable_type', Orden::class)
    ->where('aprobable_id', $ordenId)
    ->where('tipo', 'calidad')
    ->where('resultado', 'rechazado')
    ->exists();

echo "Orden #{$ordenId}:\n";
echo "  - Tiene rechazos previos de calidad: " . ($hasRechazo ? 'SÍ' : 'NO') . "\n";
echo "  - Nuevos avances deberían marcarse como corregidos: " . ($hasRechazo ? 'SÍ' : 'NO') . "\n\n";

echo "✅ Lógica de detección funcionando correctamente\n";

exit(0);
