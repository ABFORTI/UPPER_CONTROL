<?php

// Asegurarse de que estamos en el contexto correcto
$basePath = __DIR__ . '/..';
require $basePath . '/vendor/autoload.php';

$app = require_once $basePath . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Obtener el tipo de email
$tipo = $_GET['tipo'] ?? 'ot-asignada';

// Ejecutar el comando
$exitCode = \Illuminate\Support\Facades\Artisan::call('email:preview', ['tipo' => $tipo]);

// Retornar respuesta
header('Content-Type: application/json');
echo json_encode([
    'success' => $exitCode === 0,
    'tipo' => $tipo
]);
