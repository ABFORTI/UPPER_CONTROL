<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('features')->updateOrInsert(
            ['key' => 'service_customs_fields'],
            [
                'nombre' => 'Campos aduanales (SKU/Origen/Pedimento)',
                'descripcion' => 'Habilita captura de SKU, Origen y Pedimento en Información del Servicio.',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('features')->where('key', 'service_customs_fields')->delete();
    }
};
