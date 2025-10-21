<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up()
  {
    Schema::table('ordenes_trabajo', function (Blueprint $table) {
      $table->timestamp('fecha_completada')->nullable()->after('updated_at');
    });
  }

  public function down()
  {
    Schema::table('ordenes_trabajo', function (Blueprint $table) {
      $table->dropColumn('fecha_completada');
    });
  }
};
