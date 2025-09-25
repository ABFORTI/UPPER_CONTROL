<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('servicio_tamanos', function (Blueprint $t) {
            $t->enum('tamano', ['chico','mediano','grande','jumbo'])->change();
        });
    }
    public function down(): void
    {
        Schema::table('servicio_tamanos', function (Blueprint $t) {
            $t->enum('tamano', ['chico','mediano','grande'])->change();
        });
    }
};
