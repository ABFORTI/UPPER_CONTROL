<?php

// database/migrations/xxxx_xx_xx_alter_ordenes_add_calidad_cliente.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::table('ordenes_trabajo', function (Blueprint $t) {
      $t->enum('calidad_resultado',['pendiente','validado','rechazado'])
        ->default('pendiente')->after('estatus');
      $t->timestamp('cliente_autorizada_at')->nullable()->after('calidad_resultado');
    });
  }
  public function down(): void {
    Schema::table('ordenes_trabajo', function (Blueprint $t) {
      $t->dropColumn(['calidad_resultado','cliente_autorizada_at']);
    });
  }
};
