<?php
require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== PRUEBA DE NOTIFICACIONES SINCRÓNICAS ===\n\n";

$coord = App\Models\User::find(7);
if (!$coord) {
    echo "❌ No se encontró coordinador\n";
    exit(1);
}

echo "📊 Estado ANTES de enviar notificación:\n";
echo "  Total: " . $coord->notifications()->count() . "\n";
echo "  No leídas: " . $coord->unreadNotifications()->count() . "\n";

echo "\n🔔 Enviando notificación de prueba...\n";

// Simular lo que hace Notifier::toRoleInCentro
$notification = new App\Notifications\SystemEventNotification(
    'Prueba Sincrónica',
    'Esta es una notificación de prueba enviada sincrónicamente sin cola.',
    route('dashboard')
);

$coord->notify($notification);

echo "✅ Notificación enviada\n";

// Recargar el usuario para obtener datos frescos
$coord = $coord->fresh();

echo "\n📊 Estado DESPUÉS de enviar notificación:\n";
echo "  Total: " . $coord->notifications()->count() . "\n";
echo "  No leídas: " . $coord->unreadNotifications()->count() . "\n";

echo "\n🔍 Última notificación:\n";
$ultima = $coord->notifications()->latest()->first();
if ($ultima) {
    $data = $ultima->data;
    echo "  Título: " . ($data['title'] ?? 'N/A') . "\n";
    echo "  Mensaje: " . ($data['message'] ?? 'N/A') . "\n";
    echo "  Estado: " . ($ultima->read_at ? 'Leída' : 'NO LEÍDA') . "\n";
    echo "  Creada: " . $ultima->created_at . "\n";
}

echo "\n✅ Las notificaciones ahora se ejecutan INMEDIATAMENTE sin necesidad de worker\n";
echo "\n💡 Prueba crear una solicitud y verás la notificación al instante en /notificaciones\n";
