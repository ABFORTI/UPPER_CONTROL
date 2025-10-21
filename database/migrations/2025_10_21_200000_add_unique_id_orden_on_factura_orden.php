<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Asegurar que una OT solo pueda estar ligada a una sola factura
        Schema::table('factura_orden', function (Blueprint $t) {
            // Evitar duplicados de id_orden en diferentes facturas
            $t->unique('id_orden', 'factura_orden_id_orden_unique');
        });
    }

    public function down(): void
    {
        Schema::table('factura_orden', function (Blueprint $t) {
            $t->dropUnique('factura_orden_id_orden_unique');
        });
    }
};
