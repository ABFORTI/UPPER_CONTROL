<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
  public function up(): void {
    // Alter enum to include 'facturada'
    // For MySQL, use ALTER TABLE MODIFY
    // For SQLite (testing), this will be skipped as SQLite doesn't support ENUM changes
    if (DB::getDriverName() === 'mysql') {
      DB::statement("ALTER TABLE `ordenes_trabajo` MODIFY `estatus` ENUM('generada','asignada','en_proceso','completada','autorizada_cliente','facturada') NOT NULL DEFAULT 'generada'");
    }
    // For SQLite, the column was already created as string in the base migration, 
    // so no alteration needed
  }

  public function down(): void {
    if (DB::getDriverName() === 'mysql') {
      DB::statement("ALTER TABLE `ordenes_trabajo` MODIFY `estatus` ENUM('generada','asignada','en_proceso','completada','autorizada_cliente') NOT NULL DEFAULT 'generada'");
    }
  }
};
