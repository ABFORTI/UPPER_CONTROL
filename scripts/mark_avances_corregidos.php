<?php
// scripts/mark_avances_corregidos.php
// Usage: php scripts/mark_avances_corregidos.php <orden_id>

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Aprobacion;
use App\Models\Avance;
use App\Models\Orden;

if ($argc < 2) {
    echo "Usage: php scripts/mark_avances_corregidos.php <orden_id>\n";
    exit(1);
}
$ordenId = (int) $argv[1];

$last = Aprobacion::where('aprobable_type', Orden::class)
    ->where('aprobable_id', $ordenId)
    ->where('tipo', 'calidad')
    ->where('resultado', 'rechazado')
    ->orderByDesc('created_at')
    ->first();

if (! $last) {
    echo "No 'rechazado' found for orden {$ordenId}\n";
    exit(0);
}
$ts = $last->created_at;
echo "Last rechazo at: {$ts}\n";

$affected = Avance::where('id_orden', $ordenId)
    ->where('created_at', '>=', $ts)
    ->update(['es_corregido' => 1]);

echo "Updated avances: {$affected}\n";

$rows = Avance::where('id_orden', $ordenId)
    ->orderBy('created_at')
    ->get(['id', 'created_at', 'comentario', 'es_corregido'])
    ->toArray();

echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

exit(0);
