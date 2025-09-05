<?php

// database/migrations/xxxx_create_solicitudes_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('solicitudes', function (Blueprint $t) {
      $t->id();
      $t->string('folio')->unique(); // UMX-202509-0001
      $t->foreignId('id_cliente')->constrained('users');
      $t->foreignId('id_centrotrabajo')->constrained('centros_trabajo');
      $t->foreignId('id_servicio')->constrained('servicios_empresa');

      // reglas: si usa_tamanos=true -> tamano requerido, descripcion opcional
      // si usa_tamanos=false -> descripcion requerida, tamano null
      $t->enum('tamano',['chico','mediano','grande'])->nullable();
      $t->string('descripcion')->nullable();

      $t->unsignedInteger('cantidad')->default(1);
      $t->text('notas')->nullable();

      $t->enum('estatus',['pendiente','aprobada','rechazada'])->default('pendiente');
      $t->foreignId('aprobada_por')->nullable()->constrained('users')->nullOnDelete();
      $t->timestamp('aprobada_at')->nullable();

      $t->timestamps();
      $t->index(['id_centrotrabajo','created_at']);
    });
  }
  public function down(): void { Schema::dropIfExists('solicitudes'); }
};
