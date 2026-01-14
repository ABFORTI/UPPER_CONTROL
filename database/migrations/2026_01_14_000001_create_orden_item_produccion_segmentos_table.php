<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('orden_item_produccion_segmentos', function (Blueprint $t) {
      $t->id();
      $t->foreignId('id_orden')->constrained('ordenes_trabajo')->cascadeOnDelete();
      $t->foreignId('id_item')->constrained('orden_items')->cascadeOnDelete();
      $t->foreignId('id_usuario')->nullable()->constrained('users')->nullOnDelete();
      $t->enum('tipo_tarifa', ['NORMAL','EXTRA','FIN_DE_SEMANA']);
      $t->unsignedInteger('cantidad');
      $t->decimal('precio_unitario', 12, 4);
      $t->decimal('subtotal', 14, 2);
      $t->string('nota', 500)->nullable();
      $t->timestamps();

      $t->index(['id_orden','id_item']);
      $t->index(['id_orden','tipo_tarifa']);
      $t->index(['created_at']);
    });
  }

  public function down(): void {
    Schema::dropIfExists('orden_item_produccion_segmentos');
  }
};
