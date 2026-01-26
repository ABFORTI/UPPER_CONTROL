<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cotizacion_item_servicios', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cotizacion_item_id')->constrained('cotizacion_items')->cascadeOnDelete();
            $table->foreignId('id_servicio')->constrained('servicios_empresa');

            // Copiamos el patrón de Solicitud: tamaño opcional; si el servicio usa tamaños puede ir null.
            $table->string('tamano', 20)->nullable();
            $table->json('tamanos_json')->nullable();

            // Permitir cantidad por servicio (por defecto igual a item.cantidad)
            $table->unsignedInteger('cantidad')->default(1);

            // Snapshot de precios al momento de cotizar
            $table->decimal('precio_unitario', 12, 2)->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('iva', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);

            $table->timestamps();

            $table->index(['cotizacion_item_id']);
            $table->index(['id_servicio']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cotizacion_item_servicios');
    }
};
