<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coordinador_usuarios', function (Blueprint $t) {
            $t->id();
            $t->foreignId('coordinador_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('usuario_id')->constrained('users')->cascadeOnDelete();
            $t->timestamps();

            $t->unique(['coordinador_id', 'usuario_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coordinador_usuarios');
    }
};
