<?php

// database/migrations/xxxx_create_servicios_empresa_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('servicios_empresa', function (Blueprint $t) {
      $t->id();
      $t->string('nombre')->unique();
      $t->boolean('usa_tamanos')->default(false);
      $t->timestamps();
    });
  }
  public function down(): void { Schema::dropIfExists('servicios_empresa'); }
};
