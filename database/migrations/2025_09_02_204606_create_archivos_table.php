<?php

// database/migrations/xxxx_create_archivos_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('archivos', function (Blueprint $t) {
      $t->id();
      $t->morphs('fileable'); // solicitud/orden/avance/calidad a futuro
      $t->string('path');
      $t->string('mime')->nullable();
      $t->unsignedBigInteger('size')->nullable();
      $t->string('subtipo')->nullable(); // 'cotizacion','evidencia'...
      $t->timestamps();
    });
  }
  public function down(): void { Schema::dropIfExists('archivos'); }
};
