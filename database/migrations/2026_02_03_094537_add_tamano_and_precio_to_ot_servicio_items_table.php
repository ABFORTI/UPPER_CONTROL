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
            $table->string('tamano')->nullable()->after('descripcion_item');
            $table->decimal('precio_unitario', 10, 2)->nullable()->after('completado');
            $table->decimal('subtotal', 10, 2)->nullable()->after('precio_unitario');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ot_servicio_items', function (Blueprint $table) {
            $table->dropColumn(['tamano', 'precio_unitario', 'subtotal']);
        });
    }
};
