<?php
// database/migrations/xxxx_xx_xx_xxxxxx_create_evidencias_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('evidencias', function (Blueprint $t) {
      $t->id();
      $t->unsignedBigInteger('id_orden');
      $t->unsignedBigInteger('id_item')->nullable();
      $t->unsignedBigInteger('id_avance')->nullable(); // si llevas bitácora de avances
      $t->unsignedBigInteger('id_usuario');
      $t->string('path');           // storage path (public)
      $t->string('original_name');  // nombre original
      $t->string('mime',100)->nullable();
      $t->unsignedBigInteger('size')->default(0);
      $t->timestamps();

      $t->index(['id_orden','id_item']);
    });
  }
  public function down(): void {
    Schema::dropIfExists('evidencias');
  }
};
