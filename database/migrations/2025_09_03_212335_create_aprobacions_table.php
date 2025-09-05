<?php

// database/migrations/xxxx_xx_xx_create_aprobaciones_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('aprobaciones', function (Blueprint $t) {
      $t->id();
      $t->morphs('aprobable'); // p.ej. ordenes_trabajo
      $t->enum('tipo', ['calidad','cliente']);
      $t->enum('resultado', ['aprobado','rechazado']);
      $t->text('observaciones')->nullable();
      $t->foreignId('id_usuario')->constrained('users');
      $t->timestamps();
    });
  }
  public function down(): void { Schema::dropIfExists('aprobaciones'); }
};
