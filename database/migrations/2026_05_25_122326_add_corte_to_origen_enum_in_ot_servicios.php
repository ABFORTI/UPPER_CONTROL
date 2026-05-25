<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE `ot_servicios` MODIFY COLUMN `origen` ENUM('SOLICITADO','ADICIONAL','CORTE') NOT NULL DEFAULT 'SOLICITADO'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE `ot_servicios` MODIFY COLUMN `origen` ENUM('SOLICITADO','ADICIONAL') NOT NULL DEFAULT 'SOLICITADO'");
    }
};
