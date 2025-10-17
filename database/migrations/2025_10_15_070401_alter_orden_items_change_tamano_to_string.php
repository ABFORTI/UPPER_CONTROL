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
        Schema::table('orden_items', function (Blueprint $table) {
            // Cambiar tamano de ENUM a string para más flexibilidad
            $table->string('tamano', 50)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orden_items', function (Blueprint $table) {
            // Revertir a ENUM original
            $table->enum('tamano', ['chico','mediano','grande','jumbo'])->nullable()->change();
        });
    }
};
