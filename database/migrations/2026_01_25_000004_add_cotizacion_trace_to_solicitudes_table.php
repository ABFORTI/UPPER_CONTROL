<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes', function (Blueprint $table) {
            if (!Schema::hasColumn('solicitudes', 'id_cotizacion')) {
                $table->unsignedBigInteger('id_cotizacion')->nullable()->after('id_marca');
                $table->index(['id_cotizacion']);
            }

            if (!Schema::hasColumn('solicitudes', 'id_cotizacion_item_servicio')) {
                $table->unsignedBigInteger('id_cotizacion_item_servicio')->nullable()->after('id_cotizacion');
                $table->index(['id_cotizacion_item_servicio']);
            }
        });

        Schema::table('solicitudes', function (Blueprint $table) {
            // Agregar FKs (con tolerancia en ambientes donde ya existan o fallen por motor)
            try {
                if (Schema::hasColumn('solicitudes', 'id_cotizacion')) {
                    $table->foreign('id_cotizacion')
                        ->references('id')->on('cotizaciones')
                        ->nullOnDelete();
                }
            } catch (\Throwable $e) {}

            try {
                if (Schema::hasColumn('solicitudes', 'id_cotizacion_item_servicio')) {
                    $table->foreign('id_cotizacion_item_servicio')
                        ->references('id')->on('cotizacion_item_servicios')
                        ->nullOnDelete();
                }
            } catch (\Throwable $e) {}
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes', function (Blueprint $table) {
            try { $table->dropForeign(['id_cotizacion']); } catch (\Throwable $e) {}
            try { $table->dropForeign(['id_cotizacion_item_servicio']); } catch (\Throwable $e) {}

            if (Schema::hasColumn('solicitudes', 'id_cotizacion')) {
                try { $table->dropIndex(['id_cotizacion']); } catch (\Throwable $e) {}
                $table->dropColumn('id_cotizacion');
            }
            if (Schema::hasColumn('solicitudes', 'id_cotizacion_item_servicio')) {
                try { $table->dropIndex(['id_cotizacion_item_servicio']); } catch (\Throwable $e) {}
                $table->dropColumn('id_cotizacion_item_servicio');
            }
        });
    }
};
