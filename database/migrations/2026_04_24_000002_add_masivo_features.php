<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('features')->updateOrInsert(
            ['key' => 'autorizar_masivo_cliente'],
            [
                'nombre'      => 'Autorización masiva por cliente',
                'descripcion' => 'Permite a los roles cliente (Cliente_Supervisor, Cliente_Gerente, Cliente_Autorizador_Integraciones) autorizar múltiples órdenes de trabajo al mismo tiempo desde el listado.',
                'updated_at'  => now(),
                'created_at'  => now(),
            ]
        );

        DB::table('features')->updateOrInsert(
            ['key' => 'completar_masivo_etiquetas'],
            [
                'nombre'      => 'Completar masivo (etiquetas)',
                'descripcion' => 'Permite a coordinadores y team leaders completar al 100% múltiples OTs del sistema de etiquetas de forma masiva desde el listado.',
                'updated_at'  => now(),
                'created_at'  => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('features')->whereIn('key', ['autorizar_masivo_cliente', 'completar_masivo_etiquetas'])->delete();
    }
};
