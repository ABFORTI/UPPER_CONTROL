<?php
// scripts/test_rechazo_avances.php
// Usage: php scripts/test_rechazo_avances.php <orden_id>

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Orden;
use App\Models\Aprobacion;
use App\Models\Avance;

if ($argc < 2) { echo "Usage: php scripts/test_rechazo_avances.php <orden_id>\n"; exit(1); }
$ordenId = (int)$argv[1];
$orden = Orden::find($ordenId);
if (!$orden) { echo "Orden {$ordenId} not found\n"; exit(1); }

// Simular petición de rechazo (crear Aprobacion y actualizar OT)
Aprobacion::create([
  'aprobable_type' => Orden::class,
  'aprobable_id' => $ordenId,
  'tipo' => 'calidad',
  'resultado' => 'rechazado',
  'observaciones' => 'Motivo desde script',
  'id_usuario' => 1,
]);

$orden->update(['calidad_resultado' => 'rechazado', 'motivo_rechazo' => 'Motivo desde script', 'acciones_correctivas' => 'Acciones desde script', 'estatus' => 'en_proceso']);

// Ejecutar la misma lógica que el controller para crear el avance informativo
$coment = '[RECHAZO CALIDAD] Motivo desde script | Acciones: Acciones desde script';
Avance::create([
  'id_orden' => $ordenId,
  'id_item' => null,
  'id_usuario' => 1,
  'cantidad' => 0,
  'comentario' => $coment,
  'es_corregido' => 0,
]);

$rows = Avance::where('id_orden', $ordenId)->orderByDesc('created_at')->limit(5)->get(['id','created_at','comentario','es_corregido'])->toArray();

echo json_encode($rows, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) . PHP_EOL;

exit(0);
