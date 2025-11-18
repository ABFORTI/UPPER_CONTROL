<?php
require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== ESTADO DE LA COLA ===\n\n";

$jobsCount = DB::table('jobs')->count();
echo "📋 Trabajos en cola: $jobsCount\n";

if ($jobsCount > 0) {
    echo "\n🔍 Detalles de trabajos pendientes:\n";
    $jobs = DB::table('jobs')->orderBy('created_at', 'desc')->limit(5)->get();
    foreach ($jobs as $job) {
        $payload = json_decode($job->payload, true);
        $displayName = $payload['displayName'] ?? 'Unknown';
        $created = date('Y-m-d H:i:s', $job->created_at);
        echo "  - $displayName (creado: $created)\n";
    }
}

echo "\n📊 Últimas solicitudes:\n";
$solicitudes = App\Models\Solicitud::latest()->limit(3)->get(['id', 'folio', 'created_at']);
foreach ($solicitudes as $s) {
    echo "  - #{$s->id} {$s->folio} ({$s->created_at})\n";
}

echo "\n🔔 Estado del coordinador:\n";
$coord = App\Models\User::find(7);
if ($coord) {
    echo "  Total notificaciones: " . $coord->notifications()->count() . "\n";
    echo "  No leídas: " . $coord->unreadNotifications()->count() . "\n";
}

echo "\n";
echo "⚠️  IMPORTANTE: Para que las notificaciones se procesen, ejecuta:\n";
echo "   php artisan queue:work --stop-when-empty\n";
echo "\n";
echo "💡 O mantén el worker corriendo permanentemente:\n";
echo "   .\\start-queue-worker.ps1\n";
echo "\n";
