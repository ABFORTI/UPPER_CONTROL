<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pivote CentroTrabajo <-> Feature.
     * `enabled` permite activar/desactivar por centro sin borrar historial de asignación.
     */
    public function up(): void
    {
        Schema::create('centro_feature', function (Blueprint $table) {
            $table->foreignId('centro_trabajo_id')
                ->constrained('centros_trabajo')
                ->cascadeOnDelete();

            $table->foreignId('feature_id')
                ->constrained('features')
                ->cascadeOnDelete();

            $table->boolean('enabled')->default(false);

            $table->primary(['centro_trabajo_id', 'feature_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('centro_feature');
    }
};
