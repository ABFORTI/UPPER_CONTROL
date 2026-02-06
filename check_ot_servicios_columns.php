<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Columnas de ot_servicios ===\n\n";

$columns = DB::select('SHOW COLUMNS FROM ot_servicios');

foreach ($columns as $column) {
    echo "Campo: {$column->Field} | Tipo: {$column->Type}\n";
}
