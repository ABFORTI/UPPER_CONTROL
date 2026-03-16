<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('features')->updateOrInsert(
            ['key' => 'avance_contenedor_folio'],
            [
                'nombre' => 'Contenedor / Folio en avances',
                'descripcion' => 'Permite capturar número de contenedor o folio al registrar avances y mostrarlo en el historial del servicio.',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('features')->where('key', 'avance_contenedor_folio')->delete();
    }
};
