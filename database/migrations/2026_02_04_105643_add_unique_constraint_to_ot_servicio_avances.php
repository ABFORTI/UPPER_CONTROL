<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Previene duplicados agregando un índice único compuesto.
     * Si se intenta crear dos avances con los mismos valores clave en un periodo corto,
     * la base de datos rechazará el segundo.
     */
    public function up(): void
    {
        Schema::table('ot_servicio_avances', function (Blueprint $table) {
            // Índice único en servicio + tarifa + cantidad + precio + fecha (truncada a segundo)
            // Esto previene que se creen DOS avances idénticos en el mismo segundo
            $table->unique(
                ['ot_servicio_id', 'tarifa', 'cantidad_registrada', 'precio_unitario_aplicado', 'created_at'],
                'uk_ot_servicio_avances_prevent_duplicates'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ot_servicio_avances', function (Blueprint $table) {
            $table->dropUnique('uk_ot_servicio_avances_prevent_duplicates');
        });
    }
};
