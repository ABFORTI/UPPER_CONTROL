<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('factura_orden', function (Blueprint $t) {
            $t->id();
            $t->foreignId('id_factura')->constrained('facturas')->cascadeOnDelete();
            $t->foreignId('id_orden')->constrained('ordenes_trabajo')->cascadeOnDelete();
            $t->unique(['id_factura','id_orden']);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('factura_orden');
    }
};
