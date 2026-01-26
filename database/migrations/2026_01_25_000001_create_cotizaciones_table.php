<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cotizaciones', function (Blueprint $table) {
            $table->id();

            // Folio legible para negocio (similar a solicitudes)
            $table->string('folio')->unique();

            // Quién la crea (coordinador/admin)
            $table->foreignId('created_by')->constrained('users');

            // Cliente al que se envía
            $table->foreignId('id_cliente')->constrained('users');

            // Contexto (igual que solicitud)
            $table->foreignId('id_centrotrabajo')->constrained('centros_trabajo');
            $table->foreignId('id_centrocosto')->constrained('centros_costos');
            $table->foreignId('id_marca')->nullable()->constrained('marcas')->nullOnDelete();
            $table->foreignId('id_area')->nullable()->constrained('areas')->nullOnDelete();

            // Totales (snapshot de cotización)
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('iva', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);

            // Estado de la cotización
            $table->string('estatus', 20)->default('draft');

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->text('notas')->nullable();
            $table->text('motivo_rechazo')->nullable();

            $table->timestamps();

            $table->index(['id_centrotrabajo', 'estatus', 'created_at']);
            $table->index(['id_cliente', 'estatus', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cotizaciones');
    }
};
