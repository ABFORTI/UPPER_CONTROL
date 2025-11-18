<?php
require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== ÚLTIMA NOTIFICACIÓN DEL COORDINADOR ===\n\n";

$coord = App\Models\User::find(7);
$latest = $coord->notifications()->latest()->first();

if (!$latest) {
    echo "No hay notificaciones\n";
    exit;
}

echo "ID: {$latest->id}\n";
echo "Tipo: {$latest->type}\n";
echo "Creada: {$latest->created_at}\n";
echo "Leída: " . ($latest->read_at ? "Sí ({$latest->read_at})" : "No") . "\n";
echo "\nDatos (data):\n";
echo json_encode($latest->data, JSON_PRETTY_PRINT) . "\n";
