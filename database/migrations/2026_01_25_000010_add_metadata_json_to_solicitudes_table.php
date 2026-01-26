<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes', function (Blueprint $table) {
            if (!Schema::hasColumn('solicitudes', 'metadata_json')) {
                // Evitar fallos si el ambiente no tiene tamanos_json
                if (Schema::hasColumn('solicitudes', 'tamanos_json')) {
                    $table->json('metadata_json')->nullable()->after('tamanos_json');
                } else {
                    $table->json('metadata_json')->nullable();
                }
            }

            // Asegurar trazabilidad completa si el ambiente aún no tiene la columna
            if (!Schema::hasColumn('solicitudes', 'id_cotizacion_item')) {
                if (Schema::hasColumn('solicitudes', 'id_cotizacion')) {
                    $table->unsignedBigInteger('id_cotizacion_item')->nullable()->after('id_cotizacion');
                } else {
                    $table->unsignedBigInteger('id_cotizacion_item')->nullable();
                }
                $table->index(['id_cotizacion_item']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes', function (Blueprint $table) {
            if (Schema::hasColumn('solicitudes', 'metadata_json')) {
                $table->dropColumn('metadata_json');
            }
        });
    }
};
