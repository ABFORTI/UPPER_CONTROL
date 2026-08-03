<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('features')->updateOrInsert(
            ['key' => 'solicitud_formulario_solo_servicio'],
            [
                'nombre' => 'Formulario de solicitud solo con servicio',
                'descripcion' => 'Oculta en Solicitudes/Create los campos centro de costos, marca, descripción y área para el almacén/centro.',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('features')
            ->where('key', 'solicitud_formulario_solo_servicio')
            ->delete();
    }
};

