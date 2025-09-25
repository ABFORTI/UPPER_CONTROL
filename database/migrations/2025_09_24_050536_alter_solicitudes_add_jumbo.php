<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('solicitudes', function (Blueprint $t) {
            // Agrega 'jumbo' al enum de tamaño en solicitudes (campo simple cuando usa_tamanos=false)
            $t->enum('tamano', ['chico','mediano','grande','jumbo'])->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes', function (Blueprint $t) {
            // Revierte al enum original sin 'jumbo'
            $t->enum('tamano', ['chico','mediano','grande'])->nullable()->change();
        });
    }
};
