<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ot_servicio_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ot_servicio_id')->constrained('ot_servicios')->cascadeOnDelete();
            $table->string('descripcion_item'); // descripción del item
            $table->integer('planeado')->default(0); // cantidad planeada
            $table->integer('completado')->default(0); // cantidad completada
            $table->timestamps();

            $table->index(['ot_servicio_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ot_servicio_items');
    }
};
