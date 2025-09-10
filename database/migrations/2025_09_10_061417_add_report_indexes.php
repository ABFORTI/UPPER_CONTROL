<?php

// database/migrations/xxxx_xx_xx_xxxxxx_add_report_indexes.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('ordenes_trabajo', function (Blueprint $t) {
            $t->index(['id_centrotrabajo','created_at']);
            $t->index('id_servicio');
            $t->index('team_leader_id');
            $t->index('estatus');
            $t->index('calidad_resultado');
        });
        Schema::table('facturas', function (Blueprint $t) {
            $t->index('estatus');
            $t->index('created_at');
            $t->index('id_orden');
        });
    }
    public function down(): void {
        // Laravel puede generar nombres automáticos distintos; elimina si los sabes,
        // o usa ->dropIndex([...]) con nombres si los definiste tú.
    }
};
