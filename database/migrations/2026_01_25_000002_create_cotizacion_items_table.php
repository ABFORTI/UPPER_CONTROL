<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cotizacion_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cotizacion_id')->constrained('cotizaciones')->cascadeOnDelete();

            // Producto/ítem (descripción + cantidad)
            $table->string('descripcion');
            $table->unsignedInteger('cantidad')->default(1);
            $table->text('notas')->nullable();

            $table->timestamps();

            $table->index(['cotizacion_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cotizacion_items');
    }
};
