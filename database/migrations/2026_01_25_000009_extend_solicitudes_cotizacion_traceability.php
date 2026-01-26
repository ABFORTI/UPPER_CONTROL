<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes', function (Blueprint $table) {
            if (!Schema::hasColumn('solicitudes', 'id_cotizacion_item')) {
                $table->unsignedBigInteger('id_cotizacion_item')->nullable()->after('id_cotizacion');
                $table->index(['id_cotizacion_item']);
            }
        });

        Schema::table('solicitudes', function (Blueprint $table) {
            try {
                if (Schema::hasColumn('solicitudes', 'id_cotizacion_item')) {
                    $table->foreign('id_cotizacion_item')
                        ->references('id')->on('cotizacion_items')
                        ->nullOnDelete();
                }
            } catch (Throwable $e) {
            }
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes', function (Blueprint $table) {
            try { $table->dropForeign(['id_cotizacion_item']); } catch (Throwable $e) {}

            if (Schema::hasColumn('solicitudes', 'id_cotizacion_item')) {
                try { $table->dropIndex(['id_cotizacion_item']); } catch (Throwable $e) {}
                $table->dropColumn('id_cotizacion_item');
            }
        });
    }
};
