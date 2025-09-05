<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// database/migrations/xxxx_create_servicio_tamanos_table.php
return new class extends Migration {
  public function up(): void {
    Schema::create('servicio_tamanos', function (Blueprint $t) {
      $t->id();
      $t->foreignId('id_servicio_centro')->constrained('servicios_centro')->cascadeOnDelete();
      $t->enum('tamano',['chico','mediano','grande']);
      $t->decimal('precio',10,2);
      $t->timestamps();
      $t->unique(['id_servicio_centro','tamano']);
    });
  }
  public function down(): void { Schema::dropIfExists('servicio_tamanos'); }
};
