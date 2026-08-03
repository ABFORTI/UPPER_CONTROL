<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// Algunos centros ocultan el campo "centro de costo" en la solicitud
// (feature FEATURE_OCULTAR_CENTRO_COSTO_EN_SOLICITUD), por lo que la
// columna debe volver a aceptar NULL.
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('solicitudes', 'id_centrocosto')) {
            return;
        }

        $driver = DB::connection()->getDriverName();
        if ($driver === 'mysql') {
            try {
                DB::statement('ALTER TABLE `solicitudes` MODIFY `id_centrocosto` BIGINT UNSIGNED NULL');
            } catch (\Throwable $e) {
                // ignorar si ya es nullable
            }
        } else {
            try {
                Schema::table('solicitudes', function (Blueprint $table) {
                    $table->unsignedBigInteger('id_centrocosto')->nullable()->change();
                });
            } catch (\Throwable $e) {}
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('solicitudes', 'id_centrocosto')) {
            return;
        }

        $driver = DB::connection()->getDriverName();
        if ($driver === 'mysql') {
            try {
                DB::statement('ALTER TABLE `solicitudes` MODIFY `id_centrocosto` BIGINT UNSIGNED NOT NULL');
            } catch (\Throwable $e) {}
        } else {
            try {
                Schema::table('solicitudes', function (Blueprint $table) {
                    $table->unsignedBigInteger('id_centrocosto')->nullable(false)->change();
                });
            } catch (\Throwable $e) {}
        }
    }
};
