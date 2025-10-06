<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('centro_trabajo_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('centro_trabajo_id')->constrained('centros_trabajo')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id','centro_trabajo_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('centro_trabajo_user');
    }
};
