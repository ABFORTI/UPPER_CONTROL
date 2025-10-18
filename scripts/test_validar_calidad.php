<?php
// scripts/test_validar_calidad.php
// Usage: php scripts/test_validar_calidad.php <orden_id>

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Orden;
use App\Models\Aprobacion;

if ($argc < 2) {
    echo "Usage: php scripts/test_validar_calidad.php <orden_id>\n";
    exit(1);
}
$ordenId = (int)$argv[1];

$orden = Orden::find($ordenId);
if (!$orden) { echo "Orden {$ordenId} not found\n"; exit(1); }

// Simulate a prior rejection - only for testing if none exists
$hasRechazo = Aprobacion::where('aprobable_type', Orden::class)
    ->where('aprobable_id', $ordenId)
    ->where('tipo', 'calidad')
    ->where('resultado', 'rechazado')
    ->exists();

if (! $hasRechazo) {
    Aprobacion::create([
        'aprobable_type' => Orden::class,
        'aprobable_id' => $ordenId,
        'tipo' => 'calidad',
        'resultado' => 'rechazado',
        'observaciones' => 'Mock rechazo para prueba',
        'id_usuario' => 1,
    ]);
    $orden->update(['calidad_resultado' => 'rechazado', 'motivo_rechazo' => 'Motivo de prueba', 'acciones_correctivas' => 'Acciones de prueba']);
    echo "Se creó un rechazo de prueba y se setearon motivo/acciones en OT {$ordenId}\n";
}

// Now call the controller logic: emulate validar
// We'll perform the same DB transaction that CalidadController::validar would do
use Illuminate\Support\Facades\DB;

Aprobacion::create([
  'aprobable_type'=> Orden::class,
  'aprobable_id'  => $orden->id,
  'tipo'          => 'calidad',
  'resultado'     => 'aprobado',
  'observaciones' => 'Validación automática de prueba',
  'id_usuario'    => 1,
]);

DB::transaction(function() use ($orden) {
    $orden->calidad_resultado = 'validado';
    $orden->motivo_rechazo = null;
    $orden->acciones_correctivas = null;
    $orden->save();
});

$orden->refresh();

echo "Después de validar:\n";
echo "  calidad_resultado: {$orden->calidad_resultado}\n";
echo "  motivo_rechazo: " . ($orden->motivo_rechazo ?? '[NULL]') . "\n";
echo "  acciones_correctivas: " . ($orden->acciones_correctivas ?? '[NULL]') . "\n";

exit(0);
