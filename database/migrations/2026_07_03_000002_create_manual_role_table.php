<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('manual_role', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manual_id')->constrained('manuals')->cascadeOnDelete();
            $table->string('role');
            $table->timestamps();

            $table->unique(['manual_id', 'role'], 'manual_role_unique');
            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_role');
    }
};
