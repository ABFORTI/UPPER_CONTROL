<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ot_ajustes_detalle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ot_id')->constrained('ordenes_trabajo')->cascadeOnDelete();
            $table->foreignId('ot_detalle_id')->constrained('ot_servicio_items')->cascadeOnDelete();
            $table->enum('tipo', ['extra', 'faltante']);
            $table->unsignedInteger('cantidad');
            $table->text('motivo')->nullable();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['ot_id', 'ot_detalle_id']);
            $table->index(['tipo', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ot_ajustes_detalle');
    }
};
