<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitud_servicios', function (Blueprint $table) {
            // Hacer servicio_id nullable para permitir servicios pendientes
            $table->dropForeign(['servicio_id']);
        });

        Schema::table('solicitud_servicios', function (Blueprint $table) {
            $table->foreignId('servicio_id')->nullable()->change();
            $table->foreign('servicio_id')
                  ->references('id')->on('servicios_empresa')
                  ->cascadeOnDelete();

            // Campo de estado de asignación
            $table->string('service_assignment_status', 20)->default('assigned')->after('subtotal');
        });

        // Datos existentes: todos marcados como assigned
        DB::table('solicitud_servicios')
            ->whereNotNull('servicio_id')
            ->update(['service_assignment_status' => 'assigned']);
    }

    public function down(): void
    {
        Schema::table('solicitud_servicios', function (Blueprint $table) {
            $table->dropColumn('service_assignment_status');

            $table->dropForeign(['servicio_id']);
        });

        Schema::table('solicitud_servicios', function (Blueprint $table) {
            $table->foreignId('servicio_id')->nullable(false)->change();
            $table->foreign('servicio_id')
                  ->references('id')->on('servicios_empresa')
                  ->cascadeOnDelete();
        });
    }
};
