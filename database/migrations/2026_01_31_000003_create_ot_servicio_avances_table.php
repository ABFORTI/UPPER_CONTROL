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
        Schema::create('ot_servicio_avances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ot_servicio_id')->constrained('ot_servicios')->cascadeOnDelete();
            $table->string('tarifa')->nullable(); // "Normal", "Extra", etc.
            $table->decimal('precio_unitario_aplicado', 10, 2)->nullable();
            $table->integer('cantidad_registrada')->default(0);
            $table->text('comentario')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['ot_servicio_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ot_servicio_avances');
    }
};
