<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ot_servicio_items', function (Blueprint $table) {
            $table->integer('faltante')->default(0)->after('completado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ot_servicio_items', function (Blueprint $table) {
            $table->dropColumn('faltante');
        });
    }
};
