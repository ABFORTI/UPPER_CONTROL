<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::table('avances', function (Blueprint $t) {
      $t->boolean('es_corregido')->default(false)->after('comentario');
    });
  }
  public function down(): void {
    Schema::table('avances', function (Blueprint $t) {
      $t->dropColumn('es_corregido');
    });
  }
};
