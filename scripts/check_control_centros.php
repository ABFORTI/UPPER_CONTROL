<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

foreach (['control','comercial'] as $role) {
    $users = User::role($role)->get();
    echo "Role: $role\n";
    if ($users->isEmpty()) {
        echo "  (no users)\n\n";
        continue;
    }
    foreach ($users as $u) {
        echo "  User: {$u->email} (id: {$u->id})\n";
        $centros = $u->centros()->pluck('nombre','id')->all();
        if (empty($centros)) {
            echo "    Centros: (ninguno)\n";
        } else {
            foreach ($centros as $id => $nombre) {
                echo "    - [$id] $nombre\n";
            }
        }
        echo "\n";
    }
}
