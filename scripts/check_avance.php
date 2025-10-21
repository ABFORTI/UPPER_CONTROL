<?php
// scripts/check_avance.php
// Usage: php scripts/check_avance.php <avance_id>

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Avance;

if ($argc < 2) {
    echo "Usage: php scripts/check_avance.php <avance_id>\n";
    exit(1);
}
$avanceId = (int) $argv[1];

$avance = Avance::with(['usuario', 'item'])->find($avanceId);

if (!$avance) {
    echo "Avance {$avanceId} not found\n";
    exit(1);
}

echo json_encode($avance->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

exit(0);
