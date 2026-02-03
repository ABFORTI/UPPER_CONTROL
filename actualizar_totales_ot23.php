<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$subtotal = DB::table('ot_servicios')->where('ot_id', 23)->sum('subtotal');
$iva = $subtotal * 0.16;
$total = $subtotal + $iva;

DB::table('ordenes_trabajo')->where('id', 23)->update([
    'subtotal' => $subtotal,
    'iva' => $iva,
    'total' => $total,
    'total_real' => $subtotal
]);

echo "✅ OT #23 actualizada:\n";
echo "   Subtotal: $" . number_format($subtotal, 2) . "\n";
echo "   IVA (16%): $" . number_format($iva, 2) . "\n";
echo "   Total: $" . number_format($total, 2) . "\n";
