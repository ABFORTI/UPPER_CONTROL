<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sku_servicios', function (Blueprint $t) {
            $t->id();
            $t->foreignId('id_centrotrabajo')->constrained('centros_trabajo')->cascadeOnDelete();
            $t->string('sku', 100);
            $t->foreignId('id_servicio')->constrained('servicios_empresa')->cascadeOnDelete();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();

            $t->unique(['id_centrotrabajo', 'sku']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sku_servicios');
    }
};
