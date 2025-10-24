<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('centros_costos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_centrotrabajo');
            $table->string('nombre');
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->foreign('id_centrotrabajo')
                ->references('id')->on('centros_trabajo')
                ->onDelete('cascade');
            $table->index(['id_centrotrabajo','activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('centros_costos');
    }
};
