<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Actualizando estatus de OTs multi-servicio...\n\n";

// Obtener todas las OTs que tienen servicios (multi-servicio)
$ots = DB::table('ordenes_trabajo as o')
    ->join('ot_servicios as os', 'o.id', '=', 'os.ot_id')
    ->select('o.id')
    ->distinct()
    ->get();

foreach ($ots as $ot) {
    echo "Verificando OT #{$ot->id}...\n";
    
    // Verificar si todos los servicios están completados
    $servicios = DB::table('ot_servicios')->where('ot_id', $ot->id)->get();
    $todosCompletos = true;
    
    foreach ($servicios as $servicio) {
        $itemsIncompletos = DB::table('ot_servicio_items')
            ->where('ot_servicio_id', $servicio->id)
            ->whereColumn('completado', '<', 'planeado')
            ->count();
        
        if ($itemsIncompletos > 0) {
            $todosCompletos = false;
            echo "  - Servicio #{$servicio->id} tiene items incompletos\n";
            break;
        }
    }
    
    $estatusActual = DB::table('ordenes_trabajo')->where('id', $ot->id)->value('estatus');
    
    if ($todosCompletos && $estatusActual !== 'completada') {
        DB::table('ordenes_trabajo')
            ->where('id', $ot->id)
            ->update([
                'estatus' => 'completada',
                'calidad_resultado' => 'pendiente',
                'fecha_completada' => now()
            ]);
        echo "  ✅ Marcada como COMPLETADA\n";
    } elseif (!$todosCompletos && $estatusActual === 'completada') {
        DB::table('ordenes_trabajo')
            ->where('id', $ot->id)
            ->update(['estatus' => 'en_proceso']);
        echo "  ⚠️  Revertida a EN_PROCESO (tiene items incompletos)\n";
    } else {
        echo "  ✓ Estatus correcto: {$estatusActual}\n";
    }
    echo "\n";
}

echo "✅ Proceso completado\n";
