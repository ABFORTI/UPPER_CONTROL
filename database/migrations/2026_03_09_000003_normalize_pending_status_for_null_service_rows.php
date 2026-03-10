<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // OT: si no hay servicio asignado, debe quedar pendiente y desbloqueado.
        DB::table('ot_servicios')
            ->whereNull('servicio_id')
            ->update([
                'service_assignment_status' => 'pending',
                'service_locked' => false,
            ]);

        // Solicitud: si no hay servicio seleccionado, debe quedar pendiente.
        DB::table('solicitud_servicios')
            ->whereNull('servicio_id')
            ->update([
                'service_assignment_status' => 'pending',
            ]);
    }

    public function down(): void
    {
        // No revertimos para evitar volver a un estado inconsistente.
    }
};
