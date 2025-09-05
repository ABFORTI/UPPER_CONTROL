<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// database/migrations/xxxx_create_servicios_centro_table.php
return new class extends Migration {
  public function up(): void {
    Schema::create('servicios_centro', function (Blueprint $t) {
      $t->id();
      $t->foreignId('id_centrotrabajo')->constrained('centros_trabajo')->cascadeOnDelete();
      $t->foreignId('id_servicio')->constrained('servicios_empresa')->cascadeOnDelete();
      $t->decimal('precio_base',10,2);
      $t->timestamps();
      $t->unique(['id_centrotrabajo','id_servicio']);
    });
  }
  public function down(): void { Schema::dropIfExists('servicios_centro'); }
};
