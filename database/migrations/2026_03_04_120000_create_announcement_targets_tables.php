<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('announcement_centro_trabajo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained('announcements')->cascadeOnDelete();
            $table->foreignId('centro_trabajo_id')->constrained('centros_trabajo')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['announcement_id', 'centro_trabajo_id'], 'announcement_centro_unique');
        });

        Schema::create('announcement_role', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained('announcements')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['announcement_id', 'role_id'], 'announcement_role_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_role');
        Schema::dropIfExists('announcement_centro_trabajo');
    }
};
