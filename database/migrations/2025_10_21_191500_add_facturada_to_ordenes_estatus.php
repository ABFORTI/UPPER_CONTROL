<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
  public function up(): void {
    $driver = DB::connection()->getDriverName();
    
    if ($driver === 'mysql') {
      // Alter enum to include 'facturada'
      DB::statement("ALTER TABLE `ordenes_trabajo` MODIFY `estatus` ENUM('generada','asignada','en_proceso','completada','autorizada_cliente','facturada') NOT NULL DEFAULT 'generada'");
    } else {
      // Para SQLite u otros drivers, usamos string
      Schema::table('ordenes_trabajo', function (Blueprint $table) {
        $table->string('estatus')->default('generada')->change();
      });
    }
  }

  public function down(): void {
    $driver = DB::connection()->getDriverName();
    
    if ($driver === 'mysql') {
      DB::statement("ALTER TABLE `ordenes_trabajo` MODIFY `estatus` ENUM('generada','asignada','en_proceso','completada','autorizada_cliente') NOT NULL DEFAULT 'generada'");
    } else {
      // Para SQLite, revertimos a string también
      Schema::table('ordenes_trabajo', function (Blueprint $table) {
        $table->string('estatus')->default('generada')->change();
      });
    }
  }
};
