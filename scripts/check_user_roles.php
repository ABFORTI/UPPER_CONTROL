<?php
// scripts/check_user_roles.php
// Uso: php scripts/check_user_roles.php <email>

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

if ($argc < 2) {
    echo "Uso: php scripts/check_user_roles.php <email>\n";
    echo "Ejemplo: php scripts/check_user_roles.php comercial@ejemplo.com\n";
    exit(1);
}

$email = $argv[1];
$user = User::where('email', $email)->first();

if (!$user) {
    echo "❌ Usuario no encontrado: $email\n";
    echo "\n📋 Usuarios disponibles:\n";
    foreach (User::select('id','email','name')->get() as $u) {
        echo "  - [{$u->id}] {$u->email} ({$u->name})\n";
    }
    exit(1);
}

echo "✅ Usuario encontrado: {$user->name} ({$user->email})\n";
echo "   ID: {$user->id}\n";
echo "   Centro principal: {$user->centro_trabajo_id}\n\n";

$roles = $user->roles()->pluck('name')->toArray();
echo "🎭 Roles asignados:\n";
if (empty($roles)) {
    echo "   (ninguno)\n";
} else {
    foreach ($roles as $r) {
        echo "   - $r\n";
    }
}

$centros = $user->centros()->pluck('nombre','id')->toArray();
echo "\n🏢 Centros asignados (múltiple):\n";
if (empty($centros)) {
    echo "   (ninguno)\n";
} else {
    foreach ($centros as $id => $nombre) {
        echo "   - [$id] $nombre\n";
    }
}

echo "\n";
