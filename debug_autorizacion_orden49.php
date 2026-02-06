<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

echo "🔍 DEBUG AUTORIZACIÓN CLIENTE - ORDEN 49\n";
echo str_repeat("=", 70) . "\n\n";

// 1. Obtener la orden
$orden = DB::table('ordenes_trabajo')->where('id', 49)->first();

if (!$orden) {
    echo "❌ Orden 49 no encontrada\n";
    exit(1);
}

echo "📋 DATOS DE LA ORDEN:\n";
echo "  ID: {$orden->id}\n";
echo "  Estatus: {$orden->estatus}\n";
echo "  Calidad Resultado: {$orden->calidad_resultado}\n";
echo "  Cliente Autorizada At: " . ($orden->cliente_autorizada_at ?? 'NULL') . "\n";
echo "  ID Centro Trabajo: {$orden->id_centrotrabajo}\n\n";

// 2. Verificar si tiene solicitud
$solicitud = DB::table('solicitudes_cotizacion')->where('id', $orden->id_solicitud ?? 0)->first();

echo "📝 SOLICITUD ASOCIADA:\n";
if ($solicitud) {
    echo "  ID: {$solicitud->id}\n";
    echo "  ID Cliente: {$solicitud->id_cliente}\n";
    
    $cliente = DB::table('users')->where('id', $solicitud->id_cliente)->first();
    if ($cliente) {
        echo "  Cliente: {$cliente->name} ({$cliente->email})\n";
    }
} else {
    echo "  ⚠️ NO HAY SOLICITUD ASOCIADA\n";
    echo "  id_solicitud en orden: " . ($orden->id_solicitud ?? 'NULL') . "\n";
}
echo "\n";

// 3. Verificar aprobaciones existentes
$aprobaciones = DB::table('aprobaciones')
    ->where('aprobable_type', 'App\\Models\\Orden')
    ->where('aprobable_id', 49)
    ->where('tipo', 'cliente')
    ->get();

echo "✅ APROBACIONES DE CLIENTE:\n";
if ($aprobaciones->count() > 0) {
    foreach ($aprobaciones as $apr) {
        $usuario = DB::table('users')->where('id', $apr->id_usuario)->first();
        echo "  - Resultado: {$apr->resultado}\n";
        echo "    Usuario: " . ($usuario->name ?? 'Desconocido') . "\n";
        echo "    Fecha: {$apr->created_at}\n";
    }
} else {
    echo "  ⚠️ NO HAY APROBACIONES DE CLIENTE\n";
}
echo "\n";

// 4. Verificar usuario actual (admin por defecto)
$adminUser = DB::table('users')->where('email', 'admin@example.com')->first();
if (!$adminUser) {
    $adminUser = DB::table('users')->whereRaw('JSON_CONTAINS(roles, \'["admin"]\')', [])->first();
}

if ($adminUser) {
    echo "👤 USUARIO ADMIN:\n";
    echo "  ID: {$adminUser->id}\n";
    echo "  Email: {$adminUser->email}\n";
    echo "  Centro Trabajo ID: " . ($adminUser->centro_trabajo_id ?? 'NULL') . "\n";
}

echo "\n" . str_repeat("=", 70) . "\n";
echo "🔍 DIAGNÓSTICO:\n";

if (!$solicitud) {
    echo "❌ La orden NO tiene solicitud asociada\n";
    echo "   Sin solicitud, no hay id_cliente definido\n";
    echo "   La validación en ClienteController línea 26 falla\n";
    echo "\n";
    echo "💡 SOLUCIÓN: Crear una solicitud o asociar una existente\n";
} elseif ($orden->estatus === 'autorizada_cliente') {
    echo "✅ La orden ya está autorizada por el cliente\n";
} elseif ($orden->calidad_resultado !== 'validado') {
    echo "⚠️ La orden NO está validada por calidad\n";
    echo "   Calidad resultado actual: {$orden->calidad_resultado}\n";
} else {
    echo "✅ La orden está lista para ser autorizada por el cliente\n";
}
