<?php

// database/migrations/xxxx_xx_xx_create_orden_items_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('orden_items', function (Blueprint $t) {
      $t->id();
      $t->foreignId('id_orden')->constrained('ordenes_trabajo')->cascadeOnDelete();
      $t->string('descripcion')->nullable();                 // requerido si NO usa_tamanos
      $t->enum('tamano',['chico','mediano','grande'])->nullable(); // requerido si usa_tamanos
      $t->unsignedInteger('cantidad_planeada');
      $t->unsignedInteger('cantidad_real')->default(0);
      $t->decimal('precio_unitario',10,2)->nullable();       // snapshot
      $t->decimal('subtotal',10,2)->nullable();
      $t->string('sku')->nullable();
      $t->string('marca')->nullable();
      $t->timestamps();
    });
  }
  public function down(): void { Schema::dropIfExists('orden_items'); }
};
