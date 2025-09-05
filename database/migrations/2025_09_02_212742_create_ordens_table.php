<?php

// database/migrations/xxxx_xx_xx_create_ordens_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('ordenes_trabajo', function (Blueprint $t) {
      $t->id();
      $t->foreignId('id_solicitud')->constrained('solicitudes')->cascadeOnDelete();
      $t->foreignId('id_centrotrabajo')->constrained('centros_trabajo');
      $t->foreignId('id_servicio')->constrained('servicios_empresa');
      $t->foreignId('team_leader_id')->nullable()->constrained('users')->nullOnDelete();
      $t->enum('estatus',['generada','asignada','en_proceso','completada','autorizada_cliente'])->default('generada');
      $t->decimal('total_planeado',12,2)->default(0);
      $t->decimal('total_real',12,2)->default(0);
      $t->timestamps();
      $t->index(['id_centrotrabajo','estatus','created_at']);
    });
  }
  public function down(): void { Schema::dropIfExists('ordenes_trabajo'); }
};
