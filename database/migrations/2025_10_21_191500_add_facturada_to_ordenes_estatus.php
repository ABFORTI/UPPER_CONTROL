<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
  public function up(): void {
    // Alter enum to include 'facturada'
    DB::statement("ALTER TABLE `ordenes_trabajo` MODIFY `estatus` ENUM('generada','asignada','en_proceso','completada','autorizada_cliente','facturada') NOT NULL DEFAULT 'generada'");
  }

  public function down(): void {
    DB::statement("ALTER TABLE `ordenes_trabajo` MODIFY `estatus` ENUM('generada','asignada','en_proceso','completada','autorizada_cliente') NOT NULL DEFAULT 'generada'");
  }
};
