<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orden_items', function (Blueprint $t) {
            // Agrega 'jumbo' al enum de tamaño en los items de la OT
            $t->enum('tamano', ['chico','mediano','grande','jumbo'])->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('orden_items', function (Blueprint $t) {
            // Revierte al enum original sin 'jumbo'
            $t->enum('tamano', ['chico','mediano','grande'])->nullable()->change();
        });
    }
};
