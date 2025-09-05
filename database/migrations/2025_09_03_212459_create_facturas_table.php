<?php

// database/migrations/xxxx_xx_xx_create_facturas_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('facturas', function (Blueprint $t) {
      $t->id();
      $t->foreignId('id_orden')->unique()->constrained('ordenes_trabajo')->cascadeOnDelete();
      $t->decimal('total', 12, 2)->default(0);
      $t->string('folio_externo')->nullable(); // folio del timbrado o control externo
      $t->enum('estatus',['pendiente','facturado','por_pagar','pagado'])->default('pendiente');
      $t->date('fecha_facturado')->nullable();
      $t->date('fecha_cobro')->nullable(); // cuando se realiza el cobro
      $t->date('fecha_pagado')->nullable(); // cuando se confirma pago
      $t->timestamps();
      $t->index(['estatus','created_at']);
    });
  }
  public function down(): void { Schema::dropIfExists('facturas'); }
};
