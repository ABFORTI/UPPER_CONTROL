<?php

// database/migrations/xxxx_xx_xx_create_avances_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('avances', function (Blueprint $t) {
      $t->id();
      $t->foreignId('id_orden')->constrained('ordenes_trabajo')->cascadeOnDelete();
      $t->foreignId('id_item')->nullable()->constrained('orden_items')->nullOnDelete();
      $t->foreignId('id_usuario')->constrained('users');
      $t->unsignedInteger('cantidad');
      $t->text('comentario')->nullable();
      $t->string('evidencia_url')->nullable();
      $t->timestamps();
      $t->index(['id_orden','created_at']);
    });
  }
  public function down(): void { Schema::dropIfExists('avances'); }
};
