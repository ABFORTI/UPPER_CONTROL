<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('features')->updateOrInsert(
            ['key' => 'omitir_calidad_y_enviar_a_cliente'],
            [
                'nombre' => 'Omitir calidad y enviar a autorización del cliente',
                'descripcion' => 'Omite la revisión de calidad al completar una OT y la envía directo a autorización del cliente para ese almacén/centro.',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('features')
            ->where('key', 'omitir_calidad_y_enviar_a_cliente')
            ->delete();
    }
};
