<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ot_corte_detalles', function (Blueprint $table) {
            // Hacer ot_servicio_id nullable para soportar OTs tradicionales
            $table->foreignId('ot_servicio_id')->nullable()->change();

            // Referencia a orden_items para OTs tradicionales
            $table->foreignId('orden_item_id')
                  ->nullable()
                  ->after('ot_servicio_id')
                  ->constrained('orden_items')
                  ->cascadeOnDelete();

            // Descripción snapshot del concepto (nombre del item/servicio)
            $table->string('concepto_descripcion')->nullable()->after('orden_item_id');
        });
    }

    public function down(): void
    {
        Schema::table('ot_corte_detalles', function (Blueprint $table) {
            $table->dropForeign(['orden_item_id']);
            $table->dropColumn(['orden_item_id', 'concepto_descripcion']);
            $table->foreignId('ot_servicio_id')->nullable(false)->change();
        });
    }
};

