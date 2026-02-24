<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ot_corte_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ot_corte_id')
                  ->constrained('ot_cortes')
                  ->cascadeOnDelete();

            // Referencia al concepto/servicio de la OT
            $table->foreignId('ot_servicio_id')
                  ->constrained('ot_servicios')
                  ->cascadeOnDelete();

            $table->decimal('cantidad_cortada', 12, 2)->default(0);
            $table->decimal('precio_unitario_snapshot', 10, 2)->default(0);
            $table->decimal('importe_snapshot', 14, 2)->default(0);

            $table->timestamps();

            $table->index(['ot_corte_id']);
            $table->index(['ot_servicio_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ot_corte_detalles');
    }
};
