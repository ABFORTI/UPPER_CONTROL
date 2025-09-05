<?php

// database/migrations/xxxx_xx_xx_add_centro_to_users_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::table('users', function (Blueprint $t) {
      $t->foreignId('centro_trabajo_id')->nullable()->after('id')
        ->constrained('centros_trabajo')->nullOnDelete();
      $t->string('telefono')->nullable()->after('email');
      $t->string('puesto')->nullable()->after('telefono');
    });
  }
  public function down(): void {
    Schema::table('users', function (Blueprint $t) {
      $t->dropConstrainedForeignId('centro_trabajo_id');
      $t->dropColumn(['telefono','puesto']);
    });
  }
};
