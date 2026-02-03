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
        Schema::create('ot_servicios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ot_id')->constrained('ordenes_trabajo')->cascadeOnDelete();
            $table->foreignId('servicio_id')->constrained('servicios_empresa')->restrictOnDelete();
            $table->string('tipo_cobro')->default('pieza'); // pieza, pallet, hora, kg, etc.
            $table->integer('cantidad')->default(1);
            $table->decimal('precio_unitario', 10, 2)->default(0);
            $table->decimal('subtotal', 12, 2)->default(0); // cantidad * precio_unitario
            $table->timestamps();

            $table->index(['ot_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ot_servicios');
    }
};
