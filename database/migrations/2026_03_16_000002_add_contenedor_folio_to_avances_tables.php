<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('avances') && !Schema::hasColumn('avances', 'contenedor_folio')) {
            Schema::table('avances', function (Blueprint $table) {
                $table->string('contenedor_folio', 50)->nullable()->after('comentario');
            });
        }

        if (Schema::hasTable('ot_servicio_avances') && !Schema::hasColumn('ot_servicio_avances', 'contenedor_folio')) {
            Schema::table('ot_servicio_avances', function (Blueprint $table) {
                $table->string('contenedor_folio', 50)->nullable()->after('comentario');
            });
        }

        // Compatibilidad con instalaciones que usen este nombre de tabla.
        if (Schema::hasTable('orden_trabajo_avances') && !Schema::hasColumn('orden_trabajo_avances', 'contenedor_folio')) {
            Schema::table('orden_trabajo_avances', function (Blueprint $table) {
                $table->string('contenedor_folio', 50)->nullable()->after('comentario');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('avances') && Schema::hasColumn('avances', 'contenedor_folio')) {
            Schema::table('avances', function (Blueprint $table) {
                $table->dropColumn('contenedor_folio');
            });
        }

        if (Schema::hasTable('ot_servicio_avances') && Schema::hasColumn('ot_servicio_avances', 'contenedor_folio')) {
            Schema::table('ot_servicio_avances', function (Blueprint $table) {
                $table->dropColumn('contenedor_folio');
            });
        }

        if (Schema::hasTable('orden_trabajo_avances') && Schema::hasColumn('orden_trabajo_avances', 'contenedor_folio')) {
            Schema::table('orden_trabajo_avances', function (Blueprint $table) {
                $table->dropColumn('contenedor_folio');
            });
        }
    }
};
