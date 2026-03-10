<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ot_servicios', function (Blueprint $table) {
            // Hacer servicio_id nullable para soportar servicios pendientes
            // Primero eliminamos la FK existente
            $table->dropForeign(['servicio_id']);
        });

        Schema::table('ot_servicios', function (Blueprint $table) {
            // Re-crear servicio_id como nullable con FK
            $table->foreignId('servicio_id')->nullable()->change();
            $table->foreign('servicio_id')
                  ->references('id')->on('servicios_empresa')
                  ->restrictOnDelete();

            // Campos por ítem de servicio
            $table->string('sku')->nullable()->after('nota');
            $table->string('origen_customs')->nullable()->after('sku');
            $table->string('pedimento')->nullable()->after('origen_customs');

            // Campos de control de asignación pendiente
            $table->string('service_assignment_status', 20)->default('assigned')->after('pedimento');
            $table->boolean('service_locked')->default(true)->after('service_assignment_status');
            $table->timestamp('service_assigned_at')->nullable()->after('service_locked');
            $table->foreignId('service_assigned_by')->nullable()->after('service_assigned_at');
            $table->foreign('service_assigned_by')
                  ->references('id')->on('users')
                  ->nullOnDelete();
        });

        // Marcar todos los registros existentes como assigned y locked (compatibilidad)
        DB::table('ot_servicios')
            ->whereNotNull('servicio_id')
            ->update([
                'service_assignment_status' => 'assigned',
                'service_locked' => true,
            ]);
    }

    public function down(): void
    {
        Schema::table('ot_servicios', function (Blueprint $table) {
            $table->dropForeign(['service_assigned_by']);
            $table->dropColumn([
                'sku',
                'origen_customs',
                'pedimento',
                'service_assignment_status',
                'service_locked',
                'service_assigned_at',
                'service_assigned_by',
            ]);

            // Restaurar FK como not-nullable
            $table->dropForeign(['servicio_id']);
        });

        Schema::table('ot_servicios', function (Blueprint $table) {
            $table->foreignId('servicio_id')->nullable(false)->change();
            $table->foreign('servicio_id')
                  ->references('id')->on('servicios_empresa')
                  ->restrictOnDelete();
        });
    }
};
