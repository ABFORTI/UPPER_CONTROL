<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('features')->updateOrInsert(
            ['key' => 'solicitud_ocultar_centro_costo'],
            [
                'nombre' => 'Ocultar centro de costos en solicitud',
                'descripcion' => 'Oculta el campo Centro de Costos en el formulario de creación de solicitudes para el almacén/centro.',
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );

        DB::table('features')->updateOrInsert(
            ['key' => 'solicitud_ocultar_marca'],
            [
                'nombre' => 'Ocultar marca en solicitud',
                'descripcion' => 'Oculta el campo Marca en el formulario de creación de solicitudes para el almacén/centro.',
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );

        DB::table('features')->updateOrInsert(
            ['key' => 'solicitud_ocultar_descripcion'],
            [
                'nombre' => 'Ocultar descripción en solicitud',
                'descripcion' => 'Oculta los campos de descripción en el formulario de creación de solicitudes para el almacén/centro.',
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );

        DB::table('features')->updateOrInsert(
            ['key' => 'solicitud_ocultar_area'],
            [
                'nombre' => 'Ocultar área en solicitud',
                'descripcion' => 'Oculta el campo Área en el formulario de creación de solicitudes para el almacén/centro.',
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );
    }

    public function down(): void
    {
        DB::table('features')
            ->whereIn('key', [
                'solicitud_ocultar_centro_costo',
                'solicitud_ocultar_marca',
                'solicitud_ocultar_descripcion',
                'solicitud_ocultar_area',
            ])
            ->delete();
    }
};

