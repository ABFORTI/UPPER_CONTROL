<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('features')->updateOrInsert(
            ['key' => 'subir_excel_productos'],
            [
                'nombre' => 'Carga masiva de solicitudes por Excel',
                'descripcion' => 'Permite subir Excel con columnas PO, SKU, VPN, Marca, QTY, Pedimento y Notas para crear una solicitud con múltiples registros.',
            ]
        );
    }

    public function down(): void
    {
        DB::table('features')->where('key', 'subir_excel_productos')->delete();
    }
};
