<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Resetear faltante del item 82
$item = \App\Models\OTServicioItem::find(82);
echo "Item 82 - Faltante actual: " . ($item->faltante ?? 'NULL') . "\n";

$item->faltante = 0;
$result = $item->save();

echo "Save result: " . ($result ? 'SUCCESS' : 'FAILED') . "\n";

// Verificar
$item->refresh();
echo "Item 82 - Faltante después de reset: " . ($item->faltante ?? 'NULL') . "\n";
