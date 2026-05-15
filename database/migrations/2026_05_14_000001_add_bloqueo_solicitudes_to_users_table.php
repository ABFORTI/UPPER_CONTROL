<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('bloqueado_solicitudes')
                ->default(false)
                ->after('activo');

            $table->text('motivo_bloqueo_solicitudes')
                ->nullable()
                ->after('bloqueado_solicitudes');

            $table->timestamp('bloqueado_solicitudes_en')
                ->nullable()
                ->after('motivo_bloqueo_solicitudes');

            $table->foreignId('bloqueado_solicitudes_por')
                ->nullable()
                ->after('bloqueado_solicitudes_en')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['bloqueado_solicitudes_por']);
            $table->dropColumn([
                'bloqueado_solicitudes',
                'motivo_bloqueo_solicitudes',
                'bloqueado_solicitudes_en',
                'bloqueado_solicitudes_por',
            ]);
        });
    }
};
