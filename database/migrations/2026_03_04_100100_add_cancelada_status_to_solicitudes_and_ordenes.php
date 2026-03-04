<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        if (Schema::hasTable('solicitudes')) {
            DB::statement("ALTER TABLE `solicitudes` MODIFY `estatus` ENUM('pendiente','aprobada','rechazada','cancelada') NOT NULL DEFAULT 'pendiente'");
        }

        if (Schema::hasTable('ordenes_trabajo')) {
            DB::statement("ALTER TABLE `ordenes_trabajo` MODIFY `estatus` ENUM('generada','asignada','en_proceso','completada','autorizada_cliente','facturada','cancelada') NOT NULL DEFAULT 'generada'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        if (Schema::hasTable('solicitudes')) {
            DB::statement("ALTER TABLE `solicitudes` MODIFY `estatus` ENUM('pendiente','aprobada','rechazada') NOT NULL DEFAULT 'pendiente'");
        }

        if (Schema::hasTable('ordenes_trabajo')) {
            DB::statement("ALTER TABLE `ordenes_trabajo` MODIFY `estatus` ENUM('generada','asignada','en_proceso','completada','autorizada_cliente','facturada') NOT NULL DEFAULT 'generada'");
        }
    }
};
