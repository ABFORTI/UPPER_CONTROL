<?php
require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== VERIFICANDO NOTIFICACIONES ===\n\n";

// 1. Contar trabajos pendientes en la cola
$jobsCount = DB::table('jobs')->count();
echo "Trabajos en cola: $jobsCount\n";

// 2. Contar notificaciones totales
$notificationsCount = DB::table('notifications')->count();
echo "Notificaciones totales: $notificationsCount\n";

// 3. Buscar coordinador
$coordinador = App\Models\User::where('email', 'coordinador@gmail.com')->first();
if (!$coordinador) {
    echo "\n❌ No se encontró usuario coordinador@gmail.com\n";
    exit(1);
}

echo "\nCoordinador encontrado:\n";
echo "  ID: {$coordinador->id}\n";
echo "  Nombre: {$coordinador->name}\n";
echo "  Email: {$coordinador->email}\n";

// 4. Notificaciones del coordinador
$coordNotifications = $coordinador->notifications()->count();
$coordUnread = $coordinador->unreadNotifications()->count();

echo "\nNotificaciones del coordinador:\n";
echo "  Total: $coordNotifications\n";
echo "  No leídas: $coordUnread\n";

// 5. Últimas 5 notificaciones
echo "\nÚltimas 5 notificaciones del coordinador:\n";
$latest = $coordinador->notifications()->latest()->limit(5)->get();
if ($latest->isEmpty()) {
    echo "  (ninguna)\n";
} else {
    foreach ($latest as $n) {
        $data = $n->data;
        $title = $data['title'] ?? 'sin título';
        $message = $data['message'] ?? 'sin mensaje';
        $readStatus = $n->read_at ? '✓ leída' : '✗ no leída';
        echo "  - [$readStatus] $title: $message\n";
        echo "    Creada: {$n->created_at}\n";
    }
}

echo "\n=== FIN ===\n";
