<?php

// database/migrations/xxxx_xx_xx_create_centros_trabajo_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('centros_trabajo', function (Blueprint $t) {
      $t->id();
      $t->string('nombre');
      $t->string('prefijo')->unique(); // UMX, UGDL, etc.
      $t->timestamps();
    });
  }
  public function down(): void { Schema::dropIfExists('centros_trabajo'); }
};
