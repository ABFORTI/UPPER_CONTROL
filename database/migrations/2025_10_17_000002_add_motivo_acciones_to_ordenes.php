<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ordenes_trabajo', function (Blueprint $table) {
            $table->text('motivo_rechazo')->nullable()->after('calidad_resultado')->comment('Motivo por el cual Calidad rechazó la OT');
            $table->text('acciones_correctivas')->nullable()->after('motivo_rechazo')->comment('Acciones correctivas sugeridas tras el rechazo por Calidad');
        });
    }

    public function down(): void
    {
        Schema::table('ordenes_trabajo', function (Blueprint $table) {
            $table->dropColumn(['acciones_correctivas','motivo_rechazo']);
        });
    }
};
