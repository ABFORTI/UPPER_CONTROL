<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cotizacion_audit_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cotizacion_id')->constrained('cotizaciones')->cascadeOnDelete();
            $table->string('action', 50);

            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('actor_client_id')->nullable()->constrained('users')->nullOnDelete();

            $table->json('payload')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['cotizacion_id', 'created_at']);
            $table->index(['action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cotizacion_audit_logs');
    }
};
